// Vercel serverless function: sends push notifications via Firebase Cloud
// Messaging. Lives at POST /api/notify.
//
// This runs on Vercel's FREE tier (no credit card). FCM *sending* is free —
// only running it on Firebase's own Cloud Functions costs money, which is why
// we host the sender here instead.
//
// It needs a Firebase service account, provided as the environment variable
// FIREBASE_SERVICE_ACCOUNT (the full service-account JSON, as a string).

import { cert, getApps, initializeApp } from "firebase-admin/app";
import { getAuth } from "firebase-admin/auth";
import { getFirestore } from "firebase-admin/firestore";
import { getMessaging } from "firebase-admin/messaging";

// Initialise the Admin SDK once (re-used across warm invocations).
function ensureApp() {
  if (getApps().length) return;
  const raw = process.env.FIREBASE_SERVICE_ACCOUNT;
  if (!raw) throw new Error("FIREBASE_SERVICE_ACCOUNT env var is not set");
  initializeApp({ credential: cert(JSON.parse(raw)) });
}

export default async function handler(req, res) {
  // CORS: allow the app to call this from any origin (e.g. localhost during
  // dev, or the Vercel domain in production). Requests are still authenticated
  // with a Firebase ID token below, so this is safe.
  const origin = req.headers.origin || "*";
  res.setHeader("Access-Control-Allow-Origin", origin);
  res.setHeader("Vary", "Origin");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type, Authorization");

  if (req.method === "OPTIONS") return res.status(204).end(); // preflight
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method not allowed" });
  }

  try {
    ensureApp();

    // Only signed-in users may trigger a push.
    const header = req.headers.authorization || "";
    const idToken = header.startsWith("Bearer ") ? header.slice(7) : null;
    if (!idToken) return res.status(401).json({ error: "Missing auth token" });
    const decoded = await getAuth().verifyIdToken(idToken);
    const senderUid = decoded.uid;

    const { recipientUids, title, body, icon, tag } = req.body || {};
    if (!Array.isArray(recipientUids) || recipientUids.length === 0) {
      return res.status(400).json({ error: "No recipients" });
    }

    const db = getFirestore();

    // Gather every recipient's device tokens (skip the sender's own devices).
    const entries = [];
    await Promise.all(
      recipientUids.map(async (uid) => {
        if (uid === senderUid) return;
        const snap = await db.doc(`users/${uid}`).get();
        const tokens = snap.exists ? snap.get("fcmTokens") : null;
        if (Array.isArray(tokens)) {
          tokens.forEach((token) => entries.push({ uid, token }));
        }
      })
    );
    if (entries.length === 0) return res.status(200).json({ sent: 0 });

    // FCM data values must all be strings; drop empty ones.
    const data = {};
    for (const [key, value] of Object.entries({ title, body, icon, tag, url: "/" })) {
      if (value) data[key] = String(value);
    }

    const response = await getMessaging().sendEachForMulticast({
      tokens: entries.map((e) => e.token),
      data,
    });

    // Prune tokens FCM reports as dead so the list stays clean.
    const removals = new Map(); // uid -> Set(badTokens)
    response.responses.forEach((r, i) => {
      if (r.success) return;
      const code = r.error?.code || "";
      if (
        code === "messaging/registration-token-not-registered" ||
        code === "messaging/invalid-registration-token" ||
        code === "messaging/invalid-argument"
      ) {
        const { uid, token } = entries[i];
        if (!removals.has(uid)) removals.set(uid, new Set());
        removals.get(uid).add(token);
      }
    });
    await Promise.all(
      [...removals.entries()].map(([uid, bad]) => {
        const remaining = entries
          .filter((e) => e.uid === uid && !bad.has(e.token))
          .map((e) => e.token);
        return db.doc(`users/${uid}`).update({ fcmTokens: remaining });
      })
    );

    return res.status(200).json({ sent: response.successCount });
  } catch (err) {
    console.error("notify error:", err);
    return res.status(500).json({ error: String(err?.message || err) });
  }
}
