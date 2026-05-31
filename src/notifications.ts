// Client-side push notifications.
//
//  registerForPush(uid) — ask permission, register the FCM service worker, get
//    this device's push token, and store it on users/{uid}.fcmTokens.
//  notifyRecipients(...) — after sending a message, ask our Vercel function to
//    push a notification to the recipients (works even if their app is closed).
//
// The SENDER lives in api/notify.js (a free Vercel serverless function).

import {
  getMessaging,
  getToken,
  isSupported,
  onMessage,
} from "firebase/messaging";
import { arrayRemove, arrayUnion, doc, setDoc } from "firebase/firestore";
import { app, auth, db } from "./firebase";

// Web Push (VAPID) public key.
// Firebase Console → Project settings → Cloud Messaging →
//   "Web Push certificates" → Generate key pair → paste it here
// (or set VITE_FIREBASE_VAPID_KEY in a .env file at the project root).
const VAPID_KEY =
  import.meta.env.VITE_FIREBASE_VAPID_KEY ?? "PASTE_YOUR_VAPID_KEY_HERE";

// Where the "send a push" serverless function lives.
//  - In production on Vercel, leave VITE_NOTIFY_API_URL unset → same-origin
//    "/api/notify".
//  - In local dev (npm run dev), Vite doesn't run the function, so set
//    VITE_NOTIFY_API_URL in .env to your deployed URL, e.g.
//    https://your-app.vercel.app/api/notify — the live function will push to
//    this browser too (tokens are shared via Firestore).
const NOTIFY_URL = import.meta.env.VITE_NOTIFY_API_URL ?? "/api/notify";

// FCM uses its own service worker registration, kept at a separate scope so it
// never fights with vite-plugin-pwa's service worker (which lives at "/").
const SW_URL = "/firebase-messaging-sw.js";
const SW_SCOPE = "/firebase-cloud-messaging-push-scope";

// Remember the token so we can remove it on logout.
let registeredToken: string | null = null;

export async function registerForPush(uid: string): Promise<void> {
  try {
    if (!(await isSupported()) || !("serviceWorker" in navigator)) return;

    if (!VAPID_KEY || VAPID_KEY === "PASTE_YOUR_VAPID_KEY_HERE") {
      console.warn(
        "FCM: no VAPID key set. Add it in src/notifications.ts or VITE_FIREBASE_VAPID_KEY."
      );
      return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== "granted") return;

    const registration = await navigator.serviceWorker.register(SW_URL, {
      scope: SW_SCOPE,
    });

    const messaging = getMessaging(app);
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: registration,
    });
    if (!token) return;

    registeredToken = token;
    await setDoc(
      doc(db, "users", uid),
      { fcmTokens: arrayUnion(token) },
      { merge: true }
    );

    // Foreground messages: the open tab already shows its own in-app
    // notification (see App.tsx), so we don't show another one here.
    onMessage(messaging, (payload) => {
      console.debug("FCM foreground message:", payload);
    });
  } catch (err) {
    console.error("Could not register for push notifications:", err);
  }
}

export async function unregisterPush(uid: string): Promise<void> {
  if (!registeredToken) return;
  try {
    await setDoc(
      doc(db, "users", uid),
      { fcmTokens: arrayRemove(registeredToken) },
      { merge: true }
    );
  } catch (err) {
    console.error("Could not remove push token:", err);
  } finally {
    registeredToken = null;
  }
}

interface PushContent {
  title: string;
  body: string;
  icon?: string;
  tag?: string;
}

// Ask the Vercel function to push a notification to the given users.
// Fire-and-forget: failures (e.g. running locally with no /api) are ignored.
export async function notifyRecipients(
  recipientUids: string[],
  content: PushContent
): Promise<void> {
  try {
    const user = auth.currentUser;
    if (!user || recipientUids.length === 0) return;

    const idToken = await user.getIdToken();
    await fetch(NOTIFY_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${idToken}`,
      },
      body: JSON.stringify({ recipientUids, ...content }),
    });
  } catch (err) {
    console.debug("notifyRecipients skipped:", err);
  }
}
