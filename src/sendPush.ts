// Trigger a push to the OTHER people in a chat/group — for FREE (no Blaze plan).
//
// The browser can't call FCM directly (that needs the Admin service-account
// secret), so we POST to our tiny Vercel function (api/notify.js), which holds
// the secret and does the send. We pass our Firebase ID token so the function
// can verify we really are the sender before pushing to anyone.
//
// The endpoint URL comes from VITE_PUSH_ENDPOINT (the deployed Vercel URL, e.g.
// https://wetalk-push.vercel.app/api/notify). If it's not set we fall back to a
// same-origin "/api/notify" (works when the whole app is hosted on Vercel).
// Push is always best-effort: a failure here must never break sending a message.

import { auth } from "./firebase";

const ENDPOINT = import.meta.env.VITE_PUSH_ENDPOINT || "/api/notify";

export async function notifyNewMessage(
  type: "chat" | "group",
  id: string,
): Promise<void> {
  try {
    const user = auth.currentUser;
    if (!user) return;
    const idToken = await user.getIdToken();
    await fetch(ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${idToken}`,
      },
      body: JSON.stringify({ type, id }),
    });
  } catch (err) {
    // Best-effort: never block or break the chat if push fails.
    console.debug("[push] notify failed (non-fatal):", err);
  }
}
