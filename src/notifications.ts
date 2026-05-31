// Client-side push notifications.
//
//  registerForPush(uid) — ask permission, register the FCM service worker, get
//    this device's push token, and store it on users/{uid}.fcmTokens.
//  notifyRecipients(...) — after sending a message, ask our Vercel function to
//    push a notification to the recipients (works even if their app is closed).
//
// The SENDER lives in api/notify.js (a free Vercel serverless function).

import {
  deleteToken,
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

// Remember the token so we can remove it on logout. We also persist it in
// localStorage so logout can still find (and delete) it after a page reload.
const TOKEN_KEY = "wetalk-fcm-token";
let registeredToken: string | null = null;

// getToken's push subscription fails with "no active Service Worker" if the
// worker is still installing. Wait until it's activated (with a safety timeout).
function waitUntilActive(reg: ServiceWorkerRegistration): Promise<void> {
  return new Promise((resolve) => {
    if (reg.active) return resolve();
    const done = () => {
      if (reg.active) {
        clearInterval(poll);
        clearTimeout(timer);
        resolve();
      }
    };
    const worker = reg.installing || reg.waiting;
    worker?.addEventListener("statechange", done);
    const poll = setInterval(done, 200); // safety net if events are missed
    const timer = setTimeout(() => {
      clearInterval(poll);
      resolve();
    }, 10000);
  });
}

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
    if (permission !== "granted") {
      console.warn("FCM: notification permission not granted:", permission);
      return;
    }

    const registration = await navigator.serviceWorker.register(SW_URL, {
      scope: SW_SCOPE,
    });
    // Make sure the worker is actually active before subscribing for push.
    await waitUntilActive(registration);

    const messaging = getMessaging(app);
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: registration,
    });
    if (!token) {
      console.warn("FCM: getToken returned empty.");
      return;
    }

    // Print the token so you can test delivery straight from the Firebase
    // Console (Cloud Messaging → Send test message) without the backend.
    console.log("%cFCM TOKEN:", "color:#8c7ae6;font-weight:bold", token);

    registeredToken = token;
    localStorage.setItem(TOKEN_KEY, token);
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
  // Use the in-memory token, or fall back to the one saved in localStorage
  // (survives page reloads), so logout can always find it.
  const token = registeredToken ?? localStorage.getItem(TOKEN_KEY);
  try {
    if (token) {
      // 1. Remove this device's address from the user's doc, so the sender
      //    won't push to it anymore.
      await setDoc(
        doc(db, "users", uid),
        { fcmTokens: arrayRemove(token) },
        { merge: true }
      );
    }
    // 2. Fully unsubscribe this device from FCM as well.
    if (await isSupported()) {
      try {
        await deleteToken(getMessaging(app));
      } catch {
        /* no active token — nothing to delete */
      }
    }
  } catch (err) {
    console.error("Could not remove push token:", err);
  } finally {
    registeredToken = null;
    localStorage.removeItem(TOKEN_KEY);
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
