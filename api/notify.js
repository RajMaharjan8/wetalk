// ---------------------------------------------------------------------------
// WeTalk push notifications — FREE sender on Vercel (Spark-plan friendly).
// ---------------------------------------------------------------------------
// Cloud Functions (functions/index.js) need the paid Blaze plan. This is the
// no-cost replacement: a tiny Vercel serverless function. FCM *sending* is
// always free — only the place that runs the sender used to cost money.
//
// The browser can't send FCM itself (that needs the Admin service-account
// secret, which must never ship to the client). So the client POSTs here right
// after it writes a message; this function verifies the caller, works out the
// recipients, and pushes to their saved tokens — exactly like the old daemon.
//
// Env vars (Vercel → Project → Settings → Environment Variables):
//   FIREBASE_SERVICE_ACCOUNT  the full service-account JSON as ONE string
//                             (paste the contents of envfirebase.json)
//   APP_URL                   where a clicked notification opens
//                             (default https://wetalk-b1900.web.app)
//   ALLOW_ORIGIN              browser origin allowed to call this (CORS);
//                             defaults to APP_URL
// ---------------------------------------------------------------------------

import { initializeApp, getApps, cert } from "firebase-admin/app";
import { getAuth } from "firebase-admin/auth";
import { getFirestore, FieldValue } from "firebase-admin/firestore";
import { getMessaging } from "firebase-admin/messaging";

const APP_URL = process.env.APP_URL || "https://wetalk-b1900.web.app";
const ALLOW_ORIGIN = process.env.ALLOW_ORIGIN || APP_URL;
const ICON = `${APP_URL.replace(/\/$/, "")}/pwa-192x192.png`;

// Initialise the Admin SDK once per warm instance (reused across requests).
function admin() {
  if (!getApps().length) {
    const raw = process.env.FIREBASE_SERVICE_ACCOUNT;
    if (!raw) throw new Error("FIREBASE_SERVICE_ACCOUNT env var is not set");
    initializeApp({ credential: cert(JSON.parse(raw)) });
  }
  return { auth: getAuth(), db: getFirestore(), messaging: getMessaging() };
}

async function tokensOf(db, uid) {
  const snap = await db.collection("users").doc(uid).get();
  const arr = snap.exists ? snap.data().fcmTokens : null;
  return Array.isArray(arr) ? arr.filter(Boolean) : [];
}

async function nameOf(db, uid) {
  const snap = await db.collection("users").doc(uid).get();
  return snap.exists ? snap.data().name || "Someone" : "Someone";
}

// Send {title, body} to every token the recipient uids own; prune dead tokens.
async function pushToUsers(db, messaging, recipientUids, title, body) {
  let delivered = 0;
  for (const uid of recipientUids) {
    const tokens = await tokensOf(db, uid);
    if (tokens.length === 0) continue;

    const res = await messaging.sendEachForMulticast({
      tokens,
      // Title+body live in BOTH blocks: when webpush.notification is present
      // FCM uses it verbatim for the browser, so it must carry them itself.
      notification: { title, body },
      webpush: {
        notification: { title, body, icon: ICON, badge: ICON, requireInteraction: true },
        fcmOptions: { link: APP_URL },
      },
    });
    delivered += res.successCount;

    // Only prune tokens FCM says are truly gone (never on invalid-argument,
    // which is a payload error affecting every token).
    const DEAD = new Set([
      "messaging/registration-token-not-registered",
      "messaging/invalid-registration-token",
    ]);
    const dead = [];
    res.responses.forEach((r, i) => {
      if (!r.success && DEAD.has(r.error?.code)) dead.push(tokens[i]);
    });
    if (dead.length) {
      await db
        .collection("users")
        .doc(uid)
        .set({ fcmTokens: FieldValue.arrayRemove(...dead) }, { merge: true });
    }
  }
  return delivered;
}

export default async function handler(req, res) {
  // CORS — the app may live on a different origin (e.g. Firebase Hosting).
  res.setHeader("Access-Control-Allow-Origin", ALLOW_ORIGIN);
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Authorization, Content-Type");
  if (req.method === "OPTIONS") return res.status(204).end();
  if (req.method !== "POST") return res.status(405).json({ error: "Use POST" });

  try {
    const { auth, db, messaging } = admin();

    // 1) Authenticate the caller via their Firebase ID token.
    const header = req.headers.authorization || "";
    const idToken = header.startsWith("Bearer ") ? header.slice(7) : null;
    if (!idToken) return res.status(401).json({ error: "Missing ID token" });
    const caller = await auth.verifyIdToken(idToken);

    // 2) Which conversation just got a message?
    const body =
      typeof req.body === "string" ? JSON.parse(req.body || "{}") : req.body || {};
    const { type, id } = body;
    if ((type !== "chat" && type !== "group") || !id) {
      return res.status(400).json({ error: "Body needs { type: 'chat'|'group', id }" });
    }

    if (type === "chat") {
      const snap = await db.collection("chats").doc(id).get();
      const d = snap.data();
      if (!d || !d.lastMessage) return res.status(200).json({ ok: true, skipped: true });

      const participants = Array.isArray(d.participants) ? d.participants : [];
      // The caller must be a participant AND the one who just sent — so nobody
      // can use this endpoint to spam arbitrary users.
      if (!participants.includes(caller.uid) || d.lastSenderId !== caller.uid) {
        return res.status(403).json({ error: "Not your message to notify on" });
      }

      const recipients = participants.filter((u) => u !== d.lastSenderId);
      const senderName = await nameOf(db, d.lastSenderId);
      const delivered = await pushToUsers(
        db,
        messaging,
        recipients,
        `New message from ${senderName}`,
        d.lastMessage
      );
      return res.status(200).json({ ok: true, delivered });
    }

    // type === "group"
    const snap = await db.collection("groups").doc(id).get();
    const d = snap.data();
    if (!d || !d.lastMessage) return res.status(200).json({ ok: true, skipped: true });

    const members = Array.isArray(d.members) ? d.members : [];
    if (!members.includes(caller.uid) || d.lastSenderId !== caller.uid) {
      return res.status(403).json({ error: "Not your message to notify on" });
    }

    const recipients = members.filter((u) => u !== d.lastSenderId);
    const sender = d.lastSenderName || "Someone";
    const delivered = await pushToUsers(
      db,
      messaging,
      recipients,
      d.name || "New message",
      `${sender}: ${d.lastMessage}`
    );
    return res.status(200).json({ ok: true, delivered });
  } catch (err) {
    console.error("notify error:", err);
    return res.status(500).json({ error: err?.message || "send failed" });
  }
}
