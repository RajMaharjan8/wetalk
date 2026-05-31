/* eslint-disable no-undef */
// Service worker for Firebase Cloud Messaging (FCM).
// MUST live in /public so it's served from the site root.
// It runs even when the app/site is fully closed — that's how a user gets
// notified about new messages while WeTalk isn't open.
//
// It uses the "compat" build from Google's CDN because a service worker
// can't import the app's normal ES modules.
importScripts(
  "https://www.gstatic.com/firebasejs/12.14.0/firebase-app-compat.js"
);
importScripts(
  "https://www.gstatic.com/firebasejs/12.14.0/firebase-messaging-compat.js"
);

// Same config as src/firebase.ts (these are public keys — safe to expose).
firebase.initializeApp({
  apiKey: "AIzaSyBDJKGfBCn4Mdpb63zSLpDEtzynxQHmICs",
  authDomain: "wetalk-b1900.firebaseapp.com",
  projectId: "wetalk-b1900",
  storageBucket: "wetalk-b1900.firebasestorage.app",
  messagingSenderId: "917887753810",
  appId: "1:917887753810:web:493f1f6a1083c9ad78898b",
  measurementId: "G-Z56GS7Q8RG",
});

const messaging = firebase.messaging();

// The Vercel function sends "data-only" messages, so nothing shows
// automatically — we build the notification here. We also skip it when the
// app is already open in a tab (that tab shows its own in-app notification),
// so the user never gets the same alert twice.
messaging.onBackgroundMessage(async (payload) => {
  const data = payload.data || {};

  const clients = await self.clients.matchAll({
    type: "window",
    includeUncontrolled: true,
  });
  if (clients.length > 0) return; // a tab is open — it handles the alert

  await self.registration.showNotification(data.title || "WeTalk", {
    body: data.body || "",
    icon: data.icon || "/pwa-192x192.png",
    badge: "/pwa-64x64.png",
    tag: data.tag, // groups repeated alerts for the same chat
    data: { url: data.url || "/" },
  });
});

// Focus (or open) the app when a notification is clicked.
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || "/";
  event.waitUntil(
    self.clients
      .matchAll({ type: "window", includeUncontrolled: true })
      .then((clients) => {
        for (const client of clients) {
          if ("focus" in client) return client.focus();
        }
        if (self.clients.openWindow) return self.clients.openWindow(url);
      })
  );
});
