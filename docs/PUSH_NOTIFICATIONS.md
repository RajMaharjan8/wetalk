# Push notifications (FCM) — local testing

This app already registers each browser's FCM token (`src/notifications.ts`) and
ships a background service worker (`public/firebase-messaging-sw.js`). The piece
that was missing is the **sender** — something that watches Firestore and calls
FCM when a new message is written. The browser can't do that (it needs the Admin
SDK + a service-account key), so we run it as a small local process.

## What sends the push

`scripts/push-notifier.mjs` (run with `npm run notify`):

- Connects to the real Firebase project using the service-account key
  (`./envfirebase.json`, gitignored).
- Watches the `chats` and `groups` collections.
- On each new message, sends an FCM push to the **recipient's** saved tokens
  (`users/{uid}.fcmTokens`) and prunes any dead tokens.

For staging/production this same logic becomes a Cloud Function (Firestore
trigger) so no laptop has to stay running — the send code is identical.

## Credentials

| Value | Where it lives | Secret? |
|-------|----------------|---------|
| Web-Push **VAPID** key | `.env` → `VITE_FCM_VAPID_KEY` (client) | No — public key |
| Admin **service account** | `./envfirebase.json` (daemon only) | **YES — never bundle** |

⚠️ The service-account private key must never go in a `VITE_` var: Vite inlines
those into the browser JS, which would leak it to everyone.

## Test it across two browsers (locally)

1. **Start the app:** `npm run dev` → http://localhost:5173
2. **Start the sender** (separate terminal): `npm run notify`
   You should see `🔔 Push notifier started …`.
3. **Open two different browsers** (e.g. Chrome + Edge, or two profiles) at the
   localhost URL and sign in as **two different Google accounts** — User A and
   User B. Accept the notification permission prompt in each.
   - Each browser logs its `FCM token: …` in DevTools and saves it to its user
     doc. (`localhost` counts as a secure origin, so web push works.)
4. **Send a message** from A → B. In B's browser:
   - If B's tab is **focused on that chat** → no popup (you're already looking).
   - If B's tab is **backgrounded / on another chat / minimized** → an OS
     notification appears, delivered by FCM via the service worker.
   - The `npm run notify` terminal logs `→ <uid>: 1/1 delivered`.
5. Send B → A to confirm the other direction.

### Notes / gotchas

- The browser process must be **running** (a tab can be backgrounded, but if you
  fully quit the browser there's nothing to receive the push on desktop).
- Foreground notifications come from `App.tsx` (smarter "is the user looking at
  this chat?" logic); FCM's foreground callback is intentionally silent to avoid
  showing the same message twice.
- If you don't see a token in DevTools: clear the site's notification permission,
  reload, and re-grant it.

## Production for FREE: the Vercel sender (no Blaze plan, no laptop)

Cloud Functions need the paid **Blaze** plan. The free alternative is a tiny
Vercel serverless function — `api/notify.js` — that does the exact same send.
FCM *sending* is always free; only the host that runs the sender used to cost
money. The flow:

1. The client writes the message to Firestore (as always).
2. Right after, it POSTs to the Vercel function (`src/sendPush.ts` →
   `notifyNewMessage`), passing the user's Firebase **ID token**.
3. The function verifies the token, confirms the caller really is the message's
   sender, reads the recipients' tokens, and pushes — pruning dead tokens.

Because it's client-triggered (not a Firestore trigger), no always-on process is
needed: the push fires as a side-effect of the send. Games / presence writes do
**not** trigger a push (only real text sends call `notifyNewMessage`).

### Deploy (one-time)

```bash
npm i -g vercel      # if you don't have it
vercel               # link/create a project, accept the Vite defaults
vercel --prod        # deploy
```

### Configure (Vercel → Project → Settings → Environment Variables)

| Var | Value | Notes |
|-----|-------|-------|
| `FIREBASE_SERVICE_ACCOUNT` | the **entire** contents of `envfirebase.json`, as one string | **Secret.** Never commit it. |
| `APP_URL` | `https://wetalk-b1900.web.app` | where a clicked notification opens |
| `ALLOW_ORIGIN` | `https://wetalk-b1900.web.app` | the browser origin allowed to call the function (CORS) |

Then point the app at the function: set `VITE_PUSH_ENDPOINT` in `.env` to
`https://YOUR-PROJECT.vercel.app/api/notify` and rebuild/redeploy the app.

> Tip: to paste the service account in one line: `cat envfirebase.json | tr -d '\n'`

### Why this is safe

The service-account secret lives **only** in the Vercel env (never in the
browser bundle). The endpoint requires a valid Firebase ID token and only pushes
when the caller is the actual sender of the conversation's last message, so it
can't be used to spam arbitrary users.

### Local dev with the function
`vercel dev` serves both the app and `/api/notify` on the same origin, so you
can leave `VITE_PUSH_ENDPOINT` unset (it falls back to `/api/notify`). Or keep
using `npm run notify` (the daemon) for local testing — both work.

## Alternative: the Cloud Function (needs the paid Blaze plan)

`functions/index.js` is the deployed version of the sender — same logic, running
in Google's cloud, triggered directly by Firestore writes. Once deployed you do
**not** need `npm run notify` at all; push works on staging/prod automatically.

It exports two triggers:
- `onChatMessage` — fires on `chats/{chatId}` writes (direct messages)
- `onGroupMessage` — fires on `groups/{groupId}` writes (group messages)

Cloud Functions use the project's built-in service account, so there's **no key
file to manage** in the cloud.

### Deploy

```bash
cd functions
npm install
npm run deploy          # = firebase deploy --only functions
```

Requirements:
- **Firebase Blaze (pay-as-you-go) plan.** Cloud Functions aren't available on
  the free Spark plan. The free tier on Blaze is generous; you just need a card
  on file. Upgrade at Firebase Console → ⚙️ → Usage and billing.
- Logged into the right project: `firebase use wetalk-b1900`.

After deploy, send a message — pushes go out automatically. Watch logs with:
```bash
firebase functions:log         # or:  cd functions && npm run logs
```

### Notification click target
Notifications open `APP_URL` (default `https://wetalk-b1900.web.app`). If your
staging app lives elsewhere (e.g. Vercel), set it on the functions before
deploy and the click-through will point there.

### Do I still need `npm run notify`?
No. The daemon was only to prove things locally without deploying. Once the
Cloud Function is live it fully replaces the daemon — you can ignore or delete
`scripts/push-notifier.mjs` and the `notify` script.
