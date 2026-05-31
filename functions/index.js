// ---------------------------------------------------------------------------
// WeTalk push notifications — Cloud Functions (the SERVER-SIDE SENDER).
// ---------------------------------------------------------------------------
// This replaces the local `npm run notify` daemon. It runs in Google's cloud,
// triggered directly by Firestore writes, so push works on staging/production
// with no laptop and no service-account key file (functions use the project's
// built-in service account automatically).
//
// Two triggers:
//   onChatMessage  — fires on chats/{chatId} writes (direct messages)
//   onGroupMessage — fires on groups/{groupId} writes (group messages)
// Each sends an FCM push to every recipient's saved tokens and prunes any dead
// tokens, exactly like the local daemon did.
//
// Deploy:  cd functions && npm install && npm run deploy   (needs Blaze plan)
// ---------------------------------------------------------------------------

import { onDocumentWritten } from "firebase-functions/v2/firestore";
import { setGlobalOptions } from "firebase-functions/v2";
import { logger } from "firebase-functions";
import { initializeApp } from "firebase-admin/app";
import { getFirestore, FieldValue } from "firebase-admin/firestore";
import { getMessaging } from "firebase-admin/messaging";

initializeApp();
const db = getFirestore();
const messaging = getMessaging();

setGlobalOptions({ region: "us-central1", maxInstances: 10 });

// Where clicking a notification opens. Override per environment with the
// APP_URL env var (Firebase: `firebase functions:config` / .env). The default
// is the project's Hosting URL.
const APP_URL = process.env.APP_URL || "https://wetalk-b1900.web.app";
const ICON = `${APP_URL.replace(/\/$/, "")}/pwa-192x192.png`;

async function tokensOf(uid) {
  const snap = await db.collection("users").doc(uid).get();
  const arr = snap.exists ? snap.data().fcmTokens : null;
  return Array.isArray(arr) ? arr.filter(Boolean) : [];
}

async function nameOf(uid) {
  const snap = await db.collection("users").doc(uid).get();
  return snap.exists ? snap.data().name || "Someone" : "Someone";
}

// Send `{title, body}` to every token the recipient uids own; prune dead tokens.
async function pushToUsers(recipientUids, title, body) {
  for (const uid of recipientUids) {
    const tokens = await tokensOf(uid);
    if (tokens.length === 0) {
      logger.info(`${uid} has no registered devices — skipped`);
      continue;
    }

    const res = await messaging.sendEachForMulticast({
      tokens,
      // Title+body live in BOTH blocks: when webpush.notification is present
      // FCM uses it verbatim for the browser, so it must carry them itself.
      notification: { title, body },
      webpush: {
        notification: {
          title,
          body,
          icon: ICON,
          badge: ICON,
          requireInteraction: true,
        },
        fcmOptions: { link: APP_URL },
      },
    });

    logger.info(`${uid}: ${res.successCount}/${tokens.length} delivered`);

    // Only prune tokens FCM says are truly gone (never on invalid-argument,
    // which is a payload error affecting every token).
    const DEAD = new Set([
      "messaging/registration-token-not-registered",
      "messaging/invalid-registration-token",
    ]);
    const dead = [];
    res.responses.forEach((r, i) => {
      if (r.success) return;
      const code = r.error?.code || "unknown";
      logger.warn(`token ${i} failed: ${code} — ${r.error?.message ?? ""}`);
      if (DEAD.has(code)) dead.push(tokens[i]);
    });
    if (dead.length) {
      await db
        .collection("users")
        .doc(uid)
        .set({ fcmTokens: FieldValue.arrayRemove(...dead) }, { merge: true });
      logger.info(`pruned ${dead.length} dead token(s) from ${uid}`);
    }
  }
}

// A write only counts as a "new message" if lastMessageAt advanced — otherwise
// some other field changed (e.g. a game doc, presence) and we skip.
function isNewMessage(before, after) {
  if (!after || !after.lastMessage || !after.lastSenderId) return false;
  if (before && before.lastMessageAt === after.lastMessageAt) return false;
  return true;
}

// ---- Direct chats: chats/{chatId} has participants + last* fields ----
export const onChatMessage = onDocumentWritten(
  "chats/{chatId}",
  async (event) => {
    const before = event.data?.before?.data();
    const after = event.data?.after?.data();
    if (!isNewMessage(before, after)) return;

    const recipients = (after.participants || []).filter(
      (u) => u !== after.lastSenderId
    );
    if (recipients.length === 0) return;

    const senderName = await nameOf(after.lastSenderId);
    await pushToUsers(
      recipients,
      `New message from ${senderName}`,
      after.lastMessage
    );
  }
);

// ---- Groups: groups/{groupId} has members + lastSenderName ----
export const onGroupMessage = onDocumentWritten(
  "groups/{groupId}",
  async (event) => {
    const before = event.data?.before?.data();
    const after = event.data?.after?.data();
    if (!isNewMessage(before, after)) return;

    const recipients = (after.members || []).filter(
      (u) => u !== after.lastSenderId
    );
    if (recipients.length === 0) return;

    const sender = after.lastSenderName || "Someone";
    await pushToUsers(
      recipients,
      after.name || "New message",
      `${sender}: ${after.lastMessage}`
    );
  }
);
