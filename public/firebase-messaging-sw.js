/* eslint-disable no-undef */
// Service worker for Firebase Cloud Messaging.
// MUST live in /public so it's served from the site root. It runs even when
// the app is closed, which is how a push notification is shown then.
//
// It uses the "compat" build from Google's CDN (a service worker can't import
// the app's normal ES modules).
importScripts(
  "https://www.gstatic.com/firebasejs/12.14.0/firebase-app-compat.js"
);
importScripts(
  "https://www.gstatic.com/firebasejs/12.14.0/firebase-messaging-compat.js"
);

// Same config as src/firebase.ts (these are public keys).
firebase.initializeApp({
  apiKey: "AIzaSyBDJKGfBCn4Mdpb63zSLpDEtzynxQHmICs",
  authDomain: "wetalk-b1900.firebaseapp.com",
  projectId: "wetalk-b1900",
  storageBucket: "wetalk-b1900.firebasestorage.app",
  messagingSenderId: "917887753810",
  appId: "1:917887753810:web:493f1f6a1083c9ad78898b",
  measurementId: "G-Z56GS7Q8RG",
});

// This is all that's needed: with messaging initialised, FCM automatically
// shows notification-payload messages when the app is closed/backgrounded.
firebase.messaging();
