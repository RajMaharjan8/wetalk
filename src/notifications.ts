// Firebase Cloud Messaging (push notifications) — CLIENT SIDE ONLY.
//
//   initPush(uid)   — ask permission, register the service worker, get this
//                     device's push token, save it on users/{uid}.fcmTokens,
//                     and show incoming messages while the app is open.
//   removePush(uid) — on logout, stop this device getting the user's pushes.
//
// HOW TO SEND a notification (no backend needed):
//   Firebase Console → Engage → Messaging → "Send test message" → paste a
//   token (printed in the console below) → Test.
// Later, to auto-send on every message, add a sender that reads the tokens
// from users/{uid}.fcmTokens and calls FCM.

import {
  deleteToken,
  getMessaging,
  getToken,
  isSupported,
  onMessage,
} from "firebase/messaging";
import { arrayRemove, arrayUnion, doc, setDoc } from "firebase/firestore";
import { app, db } from "./firebase";

// Public Web Push (VAPID) key — Firebase Console → Cloud Messaging → Web Push
// certificates. Safe to keep in code (it's a public key).
const VAPID_KEY =
  "BLPaUNvPiaBZQfp2qCBZG7ZyZH5ZCYu7cXRlLde-SK3-NUFeWhq6i7DNPJLToF9uNVr3U2i9_BWs6heT_ZdzWqw";

const SW_URL = "/firebase-messaging-sw.js";
// Own scope so it doesn't clash with the PWA service worker (which is at "/").
const SW_SCOPE = "/firebase-push";
const TOKEN_KEY = "wetalk-fcm-token";

export async function initPush(uid: string): Promise<void> {
  try {
    if (!(await isSupported())) return;

    const permission = await Notification.requestPermission();
    if (permission !== "granted") return;

    const registration = await navigator.serviceWorker.register(SW_URL, {
      scope: SW_SCOPE,
    });
    await waitUntilActive(registration); // avoids "no active Service Worker"

    // A leftover subscription tied to a different VAPID/applicationServerKey
    // makes the next subscribe() fail with
    // "AbortError: Registration failed - push service error". Drop it first.
    const existing = await registration.pushManager.getSubscription();
    if (existing) await existing.unsubscribe();

    const messaging = getMessaging(app);
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: registration,
    });
    if (!token) return;

    console.log("FCM token:", token); // paste this into Console to test
    localStorage.setItem(TOKEN_KEY, token);
    await setDoc(
      doc(db, "users", uid),
      { fcmTokens: arrayUnion(token) },
      { merge: true },
    );

    // App is open → show the notification ourselves (FCM only auto-shows when
    // the app is closed).
    onMessage(messaging, (payload) => {
      const n = payload.notification;
      new Notification(n?.title ?? "New message", {
        body: n?.body ?? "",
        icon: "/pwa-192x192.png",
      });
    });
  } catch (err) {
    console.error("Push init failed");
    console.error(err);
    console.error((err as any)?.name);
    console.error((err as any)?.message);
    console.error((err as any)?.code);
  }
}

export async function removePush(uid: string): Promise<void> {
  const token = localStorage.getItem(TOKEN_KEY);
  try {
    if (token) {
      await setDoc(
        doc(db, "users", uid),
        { fcmTokens: arrayRemove(token) },
        { merge: true },
      );
    }
    if (await isSupported()) {
      try {
        await deleteToken(getMessaging(app));
      } catch {
        /* no token to delete */
      }
    }
  } finally {
    localStorage.removeItem(TOKEN_KEY);
  }
}

// The push subscription fails if the worker is still installing — wait for it
// to activate (with a safety timeout).
function waitUntilActive(reg: ServiceWorkerRegistration): Promise<void> {
  return new Promise((resolve) => {
    if (reg.active) return resolve();
    const check = () => reg.active && (clearInterval(poll), resolve());
    (reg.installing || reg.waiting)?.addEventListener("statechange", check);
    const poll = setInterval(check, 200);
    setTimeout(() => (clearInterval(poll), resolve()), 8000);
  });
}
