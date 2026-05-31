# Push notifications via Vercel (free, no card)

Notifications now work even when the recipient's app is **fully closed**, using:

- `public/firebase-messaging-sw.js` — service worker that shows the notification.
- `src/notifications.ts` — registers each device's push token.
- `api/notify.js` — a **Vercel** serverless function that sends the push
  (free tier, no credit card). FCM sending is free; we just don't run it on
  Firebase's paid Cloud Functions.

Everything is coded. These one-time setup steps need your accounts.

---

## 1. Generate the VAPID key (the browser push key)

- Firebase Console → ⚙️ Project settings → **Cloud Messaging** tab
- Under **Web Push certificates**, click **Generate key pair**, copy the string.

Create a file named `.env` in the project root with:

```
VITE_FIREBASE_VAPID_KEY=paste_the_key_here
```

(`.env` is gitignored. This key is a *public* key — safe in the browser.)

## 2. Get the service account (lets the Vercel function send)

- Firebase Console → ⚙️ Project settings → **Service accounts** tab
- Click **Generate new private key** → downloads a `.json` file.
- Open it, copy the **entire** JSON contents.

You'll paste this into Vercel in step 4 — **do NOT commit it to git.**

## 3. Publish the Firestore rules (also fixes the admin page)

Either in the Firebase console (Firestore → Rules → paste → Publish), or:

```
firebase deploy --only firestore:rules
```

(The app stores each device token on `users/{uid}.fcmTokens`, which the
already-relaxed rules allow.)

## 4. Deploy to Vercel + add the env vars

Push the repo to Vercel (or `vercel` CLI). Then in the Vercel dashboard:

- Project → **Settings → Environment Variables**, add **two**:
  | Name | Value |
  |------|-------|
  | `VITE_FIREBASE_VAPID_KEY` | the VAPID key from step 1 |
  | `FIREBASE_SERVICE_ACCOUNT` | the **entire** service-account JSON from step 2 |
- **Redeploy** so the new env vars take effect.

> `VITE_…` vars are baked into the frontend at build time; `FIREBASE_SERVICE_ACCOUNT`
> is read by the serverless function at runtime. Both must be set in Vercel.

## 5. Test

1. Open the deployed Vercel URL in two browsers, sign in as two users, click
   **Allow** on the notification prompt for each.
2. **Fully close** one user's browser/tab.
3. Send them a message from the other user.
4. They get a notification. 🎉

---

## Notes & limits

- Push needs **HTTPS** — your Vercel URL is HTTPS, so good. It will NOT work on
  a plain `http://` address, and local `npm run dev` has no `/api` route (use
  the deployed site, or `vercel dev`, to test pushes).
- iOS Safari only delivers web push if the site is **installed to the home
  screen** (Add to Home Screen).
- When the app is open in a tab, that tab shows its own in-app notification and
  the service worker stays quiet — so no duplicate alerts.
