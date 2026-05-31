// ---------------------------------------------------------------------------
// LOCAL PUSH NOTIFIER  (run with: npm run notify)
// ---------------------------------------------------------------------------
// The browser side already registers each device's FCM token on
// users/{uid}.fcmTokens (see src/notifications.ts). What was missing is the
// *sender*: something that watches Firestore and actually calls FCM when a new
// message is written. That can't be done from the browser — it needs the Admin
// SDK and a service-account key.
//
// This script is that sender, running locally so you can test real push across
// two browsers BEFORE deploying anything. It connects to the real Firebase
// project (wetalk-b1900) using the service-account key, watches the `chats`
// and `groups` collections, and on every new incoming message sends an FCM
// push to the recipient's saved tokens. Invalid/expired tokens are pruned.
//
// For staging/production you wouldn't keep a laptop running — the same logic
// becomes a Cloud Function (Firestore trigger). The send code is identical.
//
//   Service-account key:  ./envfirebase.json  (already gitignored)
//                         override with  GOOGLE_APPLICATION_CREDENTIALS=/path
//   App URL for clicks:   APP_URL env (default http://localhost:5173)
// ---------------------------------------------------------------------------

import { readFileSync } from "node:fs";
import { initializeApp, cert } from "firebase-admin/app";
import { getFirestore, FieldValue } from "firebase-admin/firestore";
import { getMessaging } from "firebase-admin/messaging";

const KEY_PATH =
  process.env.GOOGLE_APPLICATION_CREDENTIALS || "./envfirebase.json";
const APP_URL = process.env.APP_URL || "http://localhost:5173";
const ICON = `${APP_URL.replace(/\/$/, "")}/pwa-192x192.png`;

let serviceAccount;
try {
  serviceAccount = JSON.parse(readFileSync(KEY_PATH, "utf8"));
} catch (err) {
  console.error(`\n✖ Could not read service-account key at "${KEY_PATH}".`);
  console.error("  Set GOOGLE_APPLICATION_CREDENTIALS or place the key there.\n");
  console.error(err.message);
  process.exit(1);
}

initializeApp({ credential: cert(serviceAccount) });
const db = getFirestore();
const messaging = getMessaging();

console.log("🔔 Push notifier started for project:", serviceAccount.project_id);
console.log("   App URL:", APP_URL);
console.log("   Watching chats + groups for new messages… (Ctrl+C to stop)\n");

// Small cache of uid -> display name so we don't re-read user docs constantly.
const nameCache = new Map();
async function nameOf(uid) {
  if (nameCache.has(uid)) return nameCache.get(uid);
  const snap = await db.collection("users").doc(uid).get();
  const name = snap.exists ? snap.data().name || "Someone" : "Someone";
  nameCache.set(uid, name);
  return name;
}

// Fetch a user's tokens. Returns [] if none.
async function tokensOf(uid) {
  const snap = await db.collection("users").doc(uid).get();
  const arr = snap.exists ? snap.data().fcmTokens : null;
  return Array.isArray(arr) ? arr.filter(Boolean) : [];
}

// Send `{title, body}` to every token a set of recipient uids owns. Prunes any
// token FCM reports as unregistered/invalid so the users collection stays clean.
async function pushToUsers(recipientUids, title, body) {
  for (const uid of recipientUids) {
    const tokens = await tokensOf(uid);
    if (tokens.length === 0) {
      console.log(`   · ${uid} has no registered devices — skipped`);
      continue;
    }

    const res = await messaging.sendEachForMulticast({
      tokens,
      notification: { title, body },
      webpush: {
        notification: { icon: ICON },
        fcmOptions: { link: APP_URL },
      },
    });

    console.log(
      `   → ${uid}: ${res.successCount}/${tokens.length} delivered` +
        (res.failureCount ? `, ${res.failureCount} failed` : "")
    );

    // Only prune tokens FCM says are genuinely gone. NOTE: we do NOT prune on
    // "invalid-argument" — that's almost always a PAYLOAD problem (same for
    // every token), so deleting on it would wipe perfectly valid tokens.
    const DEAD_CODES = new Set([
      "messaging/registration-token-not-registered",
      "messaging/invalid-registration-token",
    ]);
    const dead = [];
    res.responses.forEach((r, i) => {
      if (r.success) return;
      const code = r.error?.code || "unknown";
      const short = `${tokens[i].slice(0, 12)}…`;
      console.log(`     ✗ token ${i} (${short}) → ${code}: ${r.error?.message ?? ""}`);
      if (DEAD_CODES.has(code)) dead.push(tokens[i]);
    });
    if (dead.length) {
      await db
        .collection("users")
        .doc(uid)
        .set({ fcmTokens: FieldValue.arrayRemove(...dead) }, { merge: true });
      console.log(`     pruned ${dead.length} dead token(s) from ${uid}`);
    }
  }
}

// Generic collection watcher. `extract(id, data)` returns the bits we need, or
// null to ignore the doc. We skip the very first snapshot (everything already
// in the DB at startup) and then react only when lastMessageAt advances.
function watch(collectionName, extract) {
  const lastSeen = new Map(); // docId -> last handled lastMessageAt
  let primed = false;

  db.collection(collectionName).onSnapshot(
    (snap) => {
      for (const change of snap.docChanges()) {
        if (change.type === "removed") continue;
        const info = extract(change.doc.id, change.doc.data());
        if (!info) continue;

        const { at } = info;
        // Already handled this (or a newer) message for this doc — ignore.
        if ((lastSeen.get(change.doc.id) ?? -1) >= at) continue;
        lastSeen.set(change.doc.id, at);

        // First snapshot just records current state; don't push for old data.
        if (!primed) continue;

        handle(collectionName, info).catch((e) =>
          console.error("send error:", e.message)
        );
      }
      primed = true;
    },
    (err) => console.error(`${collectionName} listener error:`, err.message)
  );
}

async function handle(collectionName, info) {
  if (!info.recipients.length) return;
  // Direct chats leave senderName/title null — resolve the sender's name now.
  if (info.senderName == null) {
    info.senderName = await nameOf(info._senderId);
    info.title = `New message from ${info.senderName}`;
  }
  const stamp = new Date().toLocaleTimeString();
  console.log(`[${stamp}] ${collectionName}: "${info.body}" from ${info.senderName}`);
  await pushToUsers(info.recipients, info.title, info.body);
}

// ---- Direct chats: chats/{chatId} has participants + last* fields ----
watch("chats", (_id, d) => {
  if (!d || !d.lastMessage || !d.lastSenderId) return null;
  const participants = Array.isArray(d.participants) ? d.participants : [];
  const recipients = participants.filter((u) => u !== d.lastSenderId);
  return {
    at: d.lastMessageAt ?? 0,
    recipients,
    senderName: null, // resolved lazily below
    title: null,
    body: d.lastMessage,
    _senderId: d.lastSenderId,
  };
});

// ---- Groups: groups/{id} has members + lastSenderName ----
watch("groups", (_id, d) => {
  if (!d || !d.lastMessage || !d.lastSenderId) return null;
  const members = Array.isArray(d.members) ? d.members : [];
  const recipients = members.filter((u) => u !== d.lastSenderId);
  return {
    at: d.lastMessageAt ?? 0,
    recipients,
    senderName: d.lastSenderName || "Someone",
    title: d.name || "New message",
    body: `${d.lastSenderName ?? "Someone"}: ${d.lastMessage}`,
    _senderId: d.lastSenderId,
  };
});

process.on("SIGINT", () => {
  console.log("\n👋 Push notifier stopped.");
  process.exit(0);
});
