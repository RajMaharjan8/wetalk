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
// certificates. It's a PUBLIC key, so it's safe in the bundle; we read it from
// VITE_FCM_VAPID_KEY (.env) and fall back to the literal so it works either way.
const VAPID_KEY =
  import.meta.env.VITE_FCM_VAPID_KEY ||
  "BLPaUNvPiaBZQfp2qCBZG7ZyZH5ZCYu7cXRlLde-SK3-NUFeWhq6i7DNPJLToF9uNVr3U2i9_BWs6heT_ZdzWqw";

const SW_URL = "/firebase-messaging-sw.js";
// Own scope so it doesn't clash with the PWA service worker (which is at "/").
const SW_SCOPE = "/firebase-push";
const TOKEN_KEY = "wetalk-fcm-token";

// DEV-ONLY on-screen status badge so push problems are visible without opening
// DevTools. No-op in production builds. Click it to dismiss.
function pushStatus(msg: string, ok: boolean): void {
  if (!import.meta.env.DEV || typeof document === "undefined") return;
  let el = document.getElementById("push-status-badge");
  if (!el) {
    el = document.createElement("div");
    el.id = "push-status-badge";
    el.onclick = () => el?.remove();
    el.style.cssText =
      "position:fixed;bottom:10px;left:10px;z-index:99999;max-width:340px;" +
      "padding:8px 12px;border-radius:8px;font:12px/1.4 system-ui;color:#fff;" +
      "cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);white-space:pre-wrap";
    document.body.appendChild(el);
  }
  el.style.background = ok ? "#16a34a" : "#dc2626";
  el.textContent = `🔔 Push: ${msg}  (click to dismiss)`;
}

export async function initPush(uid: string): Promise<void> {
  try {
    if (!(await isSupported())) {
      console.warn("[push] FCM not supported in this browser — no token.");
      pushStatus("not supported in this browser", false);
      return;
    }

    const permission = await Notification.requestPermission();
    if (permission !== "granted") {
      console.warn(
        `[push] Notification permission is "${permission}" (need "granted"). ` +
          "Click the site-info/lock icon → Notifications → Allow, then reload.",
      );
      pushStatus(
        `permission "${permission}" — click lock icon → Notifications → Allow, reload`,
        false,
      );
      return;
    }

    const registration = await navigator.serviceWorker.register(SW_URL, {
      scope: SW_SCOPE,
    });
    await waitUntilActive(registration); // avoids "no active Service Worker"
    console.log("[push] service worker active at scope", SW_SCOPE);

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
    if (!token) {
      console.error("[push] getToken returned empty — no token registered.");
      pushStatus("getToken returned empty", false);
      return;
    }

    console.log("[push] ✅ FCM token registered:", token);
    localStorage.setItem(TOKEN_KEY, token);
    await setDoc(
      doc(db, "users", uid),
      { fcmTokens: arrayUnion(token) },
      { merge: true },
    );
    console.log("[push] token saved to users/%s.fcmTokens", uid);
    pushStatus(`registered ✓ (…${token.slice(-8)})`, true);

    // When the app is in the FOREGROUND, FCM hands the push to this callback
    // instead of showing it. We deliberately DON'T show it here — App.tsx
    // already raises an in-app notification from its Firestore listener (with
    // smarter "is the user actually looking at this chat?" logic), so showing
    // it again would double up. FCM's job is purely the BACKGROUND case, which
    // the service worker (public/firebase-messaging-sw.js) auto-displays.
    onMessage(messaging, (payload) => {
      console.debug("FCM foreground message (shown by App.tsx):", payload);
    });
  } catch (err) {
    console.error("Push init failed");
    console.error(err);
    console.error((err as any)?.name);
    console.error((err as any)?.message);
    console.error((err as any)?.code);
    pushStatus(
      `failed: ${(err as any)?.name ?? "Error"} — ${(err as any)?.message ?? err}`,
      false,
    );
  }
}

// Show a notification while the app is OPEN (foreground/backgrounded-but-running).
//
// IMPORTANT: `new Notification()` THROWS "Illegal constructor" on Android
// Chrome — the constructor only works on desktop. Mobile browsers require
// ServiceWorkerRegistration.showNotification() instead. So we always prefer the
// service worker (works on Android AND desktop) and only fall back to the
// constructor if, for some reason, no SW is available.
export async function showAppNotification(
  title: string,
  options: NotificationOptions = {},
): Promise<void> {
  try {
    if (!("Notification" in window) || Notification.permission !== "granted") {
      return;
    }
    if ("serviceWorker" in navigator) {
      // `ready` resolves to the active registration controlling this page
      // (the PWA worker at "/"). showNotification works on every platform.
      const reg = await navigator.serviceWorker.ready;
      await reg.showNotification(title, options);
      return;
    }
    // Desktop-only fallback (no service worker support).
    new Notification(title, options);
  } catch (err) {
    console.debug("[push] in-app notification failed (non-fatal):", err);
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
