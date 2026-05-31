import SendIcon from "@mui/icons-material/Send";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import DeleteIcon from "@mui/icons-material/Delete";
import CloseIcon from "@mui/icons-material/Close";
import { useContext, useEffect, useRef, useState } from "react";
import {
  addDoc,
  collection,
  deleteDoc,
  doc,
  getDocs,
  onSnapshot,
  setDoc,
} from "firebase/firestore";
import { db } from "../../firebase";
import { ThemeContext } from "../../hooks/ThemeContext";

interface ChatroomProps {
  uid: string; // the OTHER person's uid
  name: string;
  email: string;
  photoURL?: string;
  isAvailable: boolean;
  onBack: () => void;
}

interface Message {
  id: string;
  text: string;
  senderId: string;
  createdAt: number;
}

// How many of the latest messages we keep. Also the max number of
// messages you may send in a row before the other person must reply.
const MAX_MESSAGES = 4;

// Normalise a createdAt that might be a plain number (new format) OR an old
// Firestore Timestamp (old data) into milliseconds, so sorting always works.
function toMillis(value: unknown): number {
  if (typeof value === "number") return value;
  if (value && typeof (value as { toMillis?: () => number }).toMillis === "function") {
    return (value as { toMillis: () => number }).toMillis();
  }
  return 0;
}

// Both users must land on the SAME chat document, so we sort the two
// uids and join them — order-independent, identical for both sides.
function getChatId(a: string, b: string) {
  return [a, b].sort().join("_");
}

export default function Chatroom({
  uid,
  name,
  email,
  photoURL,
  isAvailable,
  onBack,
}: ChatroomProps) {
  const { currentUser } = useContext(ThemeContext);
  const myUid: string = currentUser.uid;
  const chatId = getChatId(myUid, uid);

  const [allMessages, setAllMessages] = useState<Message[]>([]);
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  // Listen to messages in real time. We sort in JavaScript (not via Firestore
  // orderBy) so it works even if old messages use the old Timestamp format.
  useEffect(() => {
    const messagesRef = collection(db, "chats", chatId, "messages");
    const unsubscribe = onSnapshot(messagesRef, (snapshot) => {
      const msgs: Message[] = snapshot.docs.map((d) => {
        const data = d.data();
        return {
          id: d.id,
          text: data.text,
          senderId: data.senderId,
          createdAt: toMillis(data.createdAt),
        };
      });
      // oldest -> newest, then keep only the latest MAX_MESSAGES
      msgs.sort((a, b) => a.createdAt - b.createdAt);
      setAllMessages(msgs.slice(-MAX_MESSAGES));
    });
    return () => unsubscribe();
  }, [chatId]);

  // auto-scroll to the newest message
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [allMessages]);

  // How many messages I've sent at the end without a reply.
  // If this hits MAX_MESSAGES, I must wait for the other person.
  const unansweredCount = (() => {
    let count = 0;
    for (let i = allMessages.length - 1; i >= 0; i--) {
      if (allMessages[i].senderId === myUid) count++;
      else break;
    }
    return count;
  })();
  const isBlocked = unansweredCount >= MAX_MESSAGES;

  const sendMessage = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const text = message.trim();
    if (!text || isBlocked || sending) return;

    setSending(true);
    setMessage("");
    try {
      // Use the device clock so the message has a real time IMMEDIATELY.
      // (serverTimestamp() is null until the server responds, which makes
      // orderBy hide the message and feels slow.)
      const now = Date.now();
      const messagesRef = collection(db, "chats", chatId, "messages");
      await addDoc(messagesRef, {
        text,
        senderId: myUid,
        createdAt: now,
      });

      // Save a summary on the chat doc so the sidebar can show a preview
      // and the global notifier knows who sent the last message.
      await setDoc(
        doc(db, "chats", chatId),
        {
          participants: [myUid, uid],
          lastMessage: text,
          lastSenderId: myUid,
          lastMessageAt: now,
        },
        { merge: true }
      );

      // Keep only the newest MAX_MESSAGES — delete the oldest beyond that.
      // Sorted in JS so old Timestamp-format messages are handled correctly
      // (and get cleaned out naturally as you chat).
      const allDocs = await getDocs(messagesRef);
      const sorted = allDocs.docs
        .map((d) => ({ ref: d.ref, at: toMillis(d.data().createdAt) }))
        .sort((a, b) => a.at - b.at); // oldest first
      const extra = sorted.slice(0, Math.max(0, sorted.length - MAX_MESSAGES));
      await Promise.all(extra.map((x) => deleteDoc(x.ref)));
    } catch (error) {
      console.error("Could not send message:", error);
    } finally {
      setSending(false);
    }
  };

  // Delete a single message (only your own ones get a delete button).
  // We work out what's left from `allMessages` (kept live by the listener)
  // instead of re-reading from the server — a getDocs right after a delete can
  // return a stale copy that still includes the just-deleted message, which
  // would leave a stale sidebar preview behind (an "orphan" chat).
  const deleteMessage = async (id: string) => {
    try {
      await deleteDoc(doc(db, "chats", chatId, "messages", id));

      const remaining = allMessages.filter((m) => m.id !== id); // already sorted
      if (remaining.length === 0) {
        // no messages left — remove the conversation entirely
        await deleteDoc(doc(db, "chats", chatId));
      } else {
        const last = remaining[remaining.length - 1]; // newest
        await setDoc(
          doc(db, "chats", chatId),
          {
            lastMessage: last.text,
            lastSenderId: last.senderId,
            lastMessageAt: last.createdAt,
          },
          { merge: true }
        );
      }
    } catch (error) {
      console.error("Could not delete message:", error);
    }
  };

  // Delete the WHOLE conversation: every message + the chat summary doc.
  // Confirmation is handled by an in-app bar (window.confirm is unreliable
  // inside embedded browsers and installed PWAs).
  const deleteChat = async () => {
    setConfirmingDelete(false);
    try {
      const messagesRef = collection(db, "chats", chatId, "messages");
      const snap = await getDocs(messagesRef);
      await Promise.all(snap.docs.map((d) => deleteDoc(d.ref)));
      await deleteDoc(doc(db, "chats", chatId));
      onBack(); // go back to the user list
    } catch (error) {
      console.error("Could not delete chat:", error);
    }
  };

  const initials = name
    .split(" ")
    .map((word) => word[0])
    .join("")
    .toUpperCase();

  return (
    <div className="bg-white h-full w-full flex flex-col">
      {/* Header */}
      <div className="w-full bg-light-bg border-b border-gray-200 shrink-0">
        <div className="flex gap-3 items-center px-4 sm:px-8 py-4">
          <button
            onClick={onBack}
            className="lg:hidden p-1 rounded-full hover:bg-light-text transition-colors shrink-0"
          >
            <ArrowBackIcon fontSize="small" className="text-gray-600" />
          </button>

          <div className="relative h-12 w-12 shrink-0">
            <div className="h-full w-full rounded-full bg-primary overflow-hidden flex justify-center items-center text-white text-sm">
              {photoURL ? (
                <img
                  src={photoURL}
                  alt={name}
                  referrerPolicy="no-referrer"
                  className="h-full w-full object-cover"
                />
              ) : (
                initials
              )}
            </div>
            {isAvailable && (
              <span className="absolute bottom-0 right-0 h-3.5 w-3.5 bg-green-500 rounded-full border-2 border-white" />
            )}
          </div>

          <div className="text-gray-600 min-w-0 flex-1">
            <h3 className="font-semibold truncate">{name}</h3>
            <div className="flex gap-2 font-light text-xs sm:text-sm">
              <span className="shrink-0">
                {isAvailable ? "Online" : "Offline"}
              </span>
              <span className="truncate">{email}</span>
            </div>
          </div>

          {/* Delete entire conversation */}
          <button
            onClick={() => setConfirmingDelete(true)}
            title="Delete chat"
            className="p-2 rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer shrink-0"
          >
            <DeleteIcon fontSize="small" />
          </button>
        </div>

        {/* In-app confirmation bar for deleting the whole conversation */}
        {confirmingDelete && (
          <div className="flex items-center gap-3 px-4 sm:px-8 py-3 bg-red-50 border-t border-red-100">
            <span className="text-sm text-red-700 flex-1">
              Delete your entire chat with {name.split(" ")[0]}? This can't be
              undone.
            </span>
            <button
              onClick={() => setConfirmingDelete(false)}
              className="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-white transition-colors cursor-pointer shrink-0"
            >
              Cancel
            </button>
            <button
              onClick={deleteChat}
              className="px-3 py-1.5 rounded-lg text-sm bg-red-600 text-white hover:bg-red-700 transition-colors cursor-pointer shrink-0"
            >
              Delete
            </button>
          </div>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-2 py-2 bg-white">
        {allMessages.length === 0 ? (
          <div className="h-full flex items-center justify-center text-gray-400 text-sm">
            No messages yet — say hi 👋
          </div>
        ) : (
          allMessages.map((msg) => {
            const isSent = msg.senderId === myUid;
            return (
              <div
                key={msg.id}
                className={`group flex items-center gap-1 ${
                  isSent ? "justify-end" : "justify-start"
                }`}
              >
                {/* delete appears on hover, only for your own messages */}
                {isSent && (
                  <button
                    onClick={() => deleteMessage(msg.id)}
                    title="Delete message"
                    className="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-gray-400 hover:text-red-600 cursor-pointer shrink-0"
                  >
                    <CloseIcon fontSize="small" />
                  </button>
                )}
                <div
                  className={`max-w-[75%] sm:max-w-xs px-4 py-2 rounded-2xl m-1.5 text-sm shadow-sm ${
                    isSent
                      ? "bg-primary text-white rounded-br-sm"
                      : "bg-light-bg text-gray-800 rounded-bl-sm"
                  }`}
                >
                  <p className="wrap-break-word">{msg.text}</p>
                </div>
              </div>
            );
          })
        )}
        <div ref={bottomRef} />
      </div>

      {/* "wait for reply" banner */}
      {isBlocked && (
        <div className="shrink-0 px-4 py-2 bg-amber-50 text-amber-700 text-xs text-center border-t border-amber-100">
          You've sent {MAX_MESSAGES} messages. Wait for {name.split(" ")[0]} to
          reply before sending more.
        </div>
      )}

      {/* Input */}
      <div className="shrink-0 p-3 bg-light-bg border-t border-gray-200">
        <form
          onSubmit={sendMessage}
          className={`flex items-center bg-white w-full h-12 border rounded-2xl overflow-hidden outline-primary has-[input:focus-within]:outline-2 ${
            isBlocked ? "border-gray-200 opacity-60" : "border-[#ddd]"
          }`}
        >
          <input
            type="text"
            value={message}
            disabled={isBlocked || sending}
            placeholder={
              isBlocked ? "Waiting for a reply..." : "Type a message..."
            }
            className="focus:outline-none flex-1 ml-4 text-sm disabled:cursor-not-allowed bg-transparent"
            onChange={(e) => setMessage(e.target.value)}
          />
          <button
            type="submit"
            disabled={isBlocked || sending || !message.trim()}
            className="px-4 sm:px-6 py-2 h-full flex gap-2 justify-center items-center bg-primary text-white hover:text-primary hover:bg-white transition-all ease-in-out cursor-pointer shrink-0 text-sm disabled:opacity-50 disabled:hover:bg-primary disabled:hover:text-white disabled:cursor-not-allowed"
          >
            <span className="hidden sm:inline">Send</span>
            <SendIcon fontSize="small" />
          </button>
        </form>
      </div>
    </div>
  );
}
