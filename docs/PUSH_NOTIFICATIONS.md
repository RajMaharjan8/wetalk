# Push notifications (FCM)

This app registers each browser's FCM token (`src/notifications.ts`) and ships a
background service worker (`public/firebase-messaging-sw.js`). The **sender** —
the thing that actually calls FCM when a message is written — can't run in the
browser (it needs the Admin SDK + a service-account secret). It runs as a tiny
**Vercel serverless function** (`api/notify.js`), deployed alongside the app.

## How it works

1. The client writes the message to Firestore (as always).
2. Right after, it POSTs to `/api/notify` (`src/sendPush.ts` → `notifyNewMessage`),
   passing the user's Firebase **ID token**.
3. The function verifies the token, confirms the caller really is the sender of
   the conversation's last message, reads the recipients' saved tokens
   (`users/{uid}.fcmTokens`), pushes, and prunes any dead tokens.

Because it's client-triggered (not a Firestore trigger), **no always-on process
is needed** — the push fires as a side-effect of the send. Games / presence
writes do **not** trigger a push (only real text sends call `notifyNewMessage`).
This keeps the project on the free Firebase **Spark** plan (no Blaze).

## Credentials

| Value | Where it lives | Secret? |
|-------|----------------|---------|
| Web-Push **VAPID** key | `VITE_FCM_VAPID_KEY` (client; public key, also has a hardcoded fallback in `notifications.ts`) | No |
| Admin **service account** | `FIREBASE_SERVICE_ACCOUNT` (Vercel env, the full `envfirebase.json` as one string) | **YES — never bundle into the client** |

⚠️ The service-account private key must never go in a `VITE_` var: Vite inlines
those into the browser JS, which would leak it to everyone.

## Production (live)

The whole app **and** `api/notify.js` are hosted together on **Vercel**
(`weebonomics/wetalk`), so `/api/notify` is same-origin.

- **Canonical domain: https://wetalk-fun.vercel.app** — use this one.
- ⚠️ The alias `wetalk-weebonomics.vercel.app` is behind **Vercel Deployment
  Protection** (shows a Vercel login page for both the app and `/api/notify`),
  so push is dead there. Either always use `wetalk-fun.vercel.app`, or disable it
  under *Vercel → Project → Settings → Deployment Protection*.

### Env vars (Vercel → Project → Settings → Environment Variables)

| Var | Value |
|-----|-------|
| `FIREBASE_SERVICE_ACCOUNT` | the **entire** contents of `envfirebase.json`, as one string |
| `APP_URL` | `https://wetalk-fun.vercel.app` (where a clicked notification opens + icon host) |
| `ALLOW_ORIGIN` | `https://wetalk-fun.vercel.app` (CORS; only matters for cross-origin callers) |
| `VITE_FCM_VAPID_KEY` | the public Web-Push VAPID key |

`VITE_PUSH_ENDPOINT` is baked into the bundle at build time
(`https://wetalk-fun.vercel.app/api/notify`); leaving it unset would fall back to
the same-origin `/api/notify`, which also works.

### Deploy

```bash
vercel --prod --yes
# then keep the stable alias pointing at the new build:
vercel alias set <new-deployment-url> wetalk-fun.vercel.app
```

A quick health check (should print `{"error":"Missing ID token"}`, which proves
the function is live and the service account initialized):

```bash
curl -s -X POST https://wetalk-fun.vercel.app/api/notify \
  -H "Content-Type: application/json" -d '{}'
```

## Local development

Plain `npm run dev` (Vite) does **not** serve `/api/notify`, so push won't fire
locally — the message still sends, the push just no-ops. Two ways to test push:

- **Easiest:** test on the deployed URL (https://wetalk-fun.vercel.app).
- **Local function:** `vercel dev` serves both the app and `/api/notify` on the
  same origin. It needs `FIREBASE_SERVICE_ACCOUNT` available locally (e.g.
  `vercel env pull`), or it will 500 on the send.

### Test across two browsers

1. Open the app in **two different browsers** (e.g. Chrome + Edge, or two
   profiles) and sign in as **two different accounts** — User A and User B.
   Accept the notification permission prompt in each.
2. **Send a message** from A → B. In B's browser:
   - tab **focused on that chat** → no popup (you're already looking);
   - tab **backgrounded / on another chat / minimized** → an OS notification
     appears, delivered by FCM via the service worker.
3. Send B → A to confirm the other direction.

### Notes / gotchas

- Foreground notifications come from `App.tsx` (smarter "is the user looking at
  this chat?" logic); FCM's foreground callback is intentionally silent to avoid
  showing the same message twice.
- If you don't see a token registered: clear the site's notification permission,
  reload, and re-grant it. (`localhost` counts as a secure origin, so web push
  works there too.)

## Alternative: Cloud Function (needs the paid Blaze plan)

`functions/index.js` is an equivalent sender that runs in Google's cloud,
triggered directly by Firestore writes — no client POST needed. It requires the
**Blaze** plan (a card on file; the free tier is generous). We deliberately use
the Vercel function instead to stay on the free Spark plan, so this is kept only
as a reference/alternative. It exports `onChatMessage` and `onGroupMessage`
triggers and uses the project's built-in service account (no key file).
