import SendIcon from "@mui/icons-material/Send";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import DeleteIcon from "@mui/icons-material/Delete";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutlineRounded";
import KeyboardArrowDownIcon from "@mui/icons-material/KeyboardArrowDown";
import CasinoIcon from "@mui/icons-material/Casino";
import SportsMmaIcon from "@mui/icons-material/SportsMma";
import VideogameAssetIcon from "@mui/icons-material/VideogameAsset";
import MonopolyGame from "./MonopolyGame";
import RockPaperScissors from "./RockPaperScissors";
import { Fragment, useContext, useEffect, useRef, useState } from "react";
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
import { notifyNewMessage } from "../../sendPush";
import { sound, haptic } from "../../lib/feedback";
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

// Max number of messages you may send in a row before the other person must
// reply. (Messages are NOT deleted — full history is kept; this is only the
// "wait for a reply" send-limit.)
const MAX_MESSAGES = 4;

// Gap after which two messages from the same sender stop being "grouped".
const GROUP_GAP_MS = 5 * 60 * 1000;

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

// ---- Time helpers (for timestamps + day separators) ----
function formatTime(ms: number): string {
  if (!ms) return "";
  return new Date(ms).toLocaleTimeString([], {
    hour: "numeric",
    minute: "2-digit",
  });
}
function dayKey(ms: number): string {
  const d = new Date(ms);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}
function formatDay(ms: number): string {
  if (!ms) return "";
  const now = new Date();
  const yesterday = new Date();
  yesterday.setDate(now.getDate() - 1);
  if (dayKey(ms) === dayKey(now.getTime())) return "Today";
  if (dayKey(ms) === dayKey(yesterday.getTime())) return "Yesterday";
  const d = new Date(ms);
  return d.toLocaleDateString([], {
    month: "short",
    day: "numeric",
    year: d.getFullYear() === now.getFullYear() ? undefined : "numeric",
  });
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
  // Which of MY messages is "tapped open" so its delete button shows. Needed
  // on touch devices, where there's no hover to reveal it. null = none.
  const [activeMsgId, setActiveMsgId] = useState<string | null>(null);
  // Which game pane is open (null = none), plus a small picker menu.
  const [activeGame, setActiveGame] = useState<"monopoly" | "rps" | null>(null);
  const [gameMenuOpen, setGameMenuOpen] = useState(false);
  // Whether the message list is scrolled near the bottom, and whether a new
  // message arrived while it wasn't (drives the floating "jump to latest").
  const [nearBottom, setNearBottom] = useState(true);
  const [hasNewBelow, setHasNewBelow] = useState(false);

  const bottomRef = useRef<HTMLDivElement>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const nearBottomRef = useRef(true);
  const prevLenRef = useRef(0);
  const initedRef = useRef(false); // have we seen the first snapshot yet?
  const firstScrollRef = useRef(false); // have we done the initial jump?

  // Listen to messages in real time. We sort in JavaScript (not via Firestore
  // orderBy) so it works even if old messages use the old Timestamp format.
  useEffect(() => {
    // Reset per-conversation tracking when switching chats.
    prevLenRef.current = 0;
    initedRef.current = false;
    firstScrollRef.current = false;
    setHasNewBelow(false);

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
      // oldest -> newest. We keep the FULL history now (no trimming).
      msgs.sort((a, b) => a.createdAt - b.createdAt);

      // A genuinely NEW incoming message (count grew + newest is theirs) →
      // gentle chime + buzz, and flag "new below" if you're scrolled up.
      if (initedRef.current && msgs.length > prevLenRef.current) {
        const newest = msgs[msgs.length - 1];
        if (newest && newest.senderId !== myUid) {
          sound.receive();
          haptic(30);
          if (!nearBottomRef.current) setHasNewBelow(true);
        }
      }
      prevLenRef.current = msgs.length;
      initedRef.current = true;
      setAllMessages(msgs);
    });
    return () => unsubscribe();
  }, [chatId, myUid]);

  // Smart auto-scroll: jump instantly on first load; afterwards only follow new
  // messages if you're already near the bottom or the message is your own.
  useEffect(() => {
    if (allMessages.length === 0) return;
    const last = allMessages[allMessages.length - 1];
    const mine = last?.senderId === myUid;
    if (!firstScrollRef.current) {
      firstScrollRef.current = true;
      bottomRef.current?.scrollIntoView();
      return;
    }
    if (mine || nearBottomRef.current) {
      bottomRef.current?.scrollIntoView({ behavior: "smooth" });
      setHasNewBelow(false);
    }
  }, [allMessages, myUid]);

  const onScroll = () => {
    const el = scrollRef.current;
    if (!el) return;
    const dist = el.scrollHeight - el.scrollTop - el.clientHeight;
    const near = dist < 120;
    nearBottomRef.current = near;
    setNearBottom(near);
    if (near && hasNewBelow) setHasNewBelow(false);
  };

  const jumpToBottom = () => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    setHasNewBelow(false);
  };

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
  const remaining = MAX_MESSAGES - unansweredCount;

  const sendMessage = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const text = message.trim();
    if (sending) return;
    if (isBlocked) {
      sound.blocked();
      haptic([20, 40, 20]);
      return;
    }
    if (!text) return;

    setSending(true);
    setMessage("");
    sound.send();
    haptic(15);
    try {
      // Use the device clock so the message has a real time IMMEDIATELY.
      // (serverTimestamp() is null until the server responds, which makes
      // orderBy hide the message and feels slow.)
      const now = Date.now();
      const messagesRef = collection(db, "chats", chatId, "messages");

      // Fire both writes in PARALLEL. The chat-summary write is what triggers
      // the other person's notification, so not waiting on the message write
      // first makes the notification land a touch sooner.
      await Promise.all([
        addDoc(messagesRef, {
          text,
          senderId: myUid,
          createdAt: now,
        }),
        // Save a summary on the chat doc so the sidebar can show a preview
        // and the global notifier knows who sent the last message.
        setDoc(
          doc(db, "chats", chatId),
          {
            participants: [myUid, uid],
            lastMessage: text,
            lastSenderId: myUid,
            lastMessageAt: now,
          },
          { merge: true }
        ),
      ]);

      // Ask our free Vercel sender to push the recipient (best-effort).
      notifyNewMessage("chat", chatId);
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
    setActiveMsgId(null);
    sound.delete();
    haptic([15, 30, 15]);
    try {
      await deleteDoc(doc(db, "chats", chatId, "messages", id));

      const remainingMsgs = allMessages.filter((m) => m.id !== id); // sorted
      if (remainingMsgs.length === 0) {
        // no messages left — remove the conversation entirely
        await deleteDoc(doc(db, "chats", chatId));
      } else {
        const last = remainingMsgs[remainingMsgs.length - 1]; // newest
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
    sound.delete();
    haptic([20, 40, 20, 40]);
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

  // A game takes over the chat pane while open.
  if (activeGame) {
    return (
      <div className="bg-white h-full w-full flex flex-col">
        {activeGame === "monopoly" ? (
          <MonopolyGame
            chatId={chatId}
            opponentUid={uid}
            opponentName={name}
            onClose={() => setActiveGame(null)}
          />
        ) : (
          <RockPaperScissors
            chatId={chatId}
            opponentUid={uid}
            opponentName={name}
            onClose={() => setActiveGame(null)}
          />
        )}
      </div>
    );
  }

  return (
    <div className="bg-white h-full w-full flex flex-col">
      {/* Header */}
      <div className="w-full bg-light-bg border-b border-gray-200 shrink-0">
        <div className="flex gap-3 items-center px-4 sm:px-8 py-4">
          <button
            onClick={onBack}
            className="lg:hidden p-1 rounded-full hover:bg-light-text transition-colors shrink-0 cursor-pointer"
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
            <div className="flex gap-2 font-light text-xs sm:text-sm items-center">
              <span className="shrink-0 flex items-center gap-1">
                <span
                  className={`h-1.5 w-1.5 rounded-full ${
                    isAvailable ? "bg-green-500" : "bg-gray-300"
                  }`}
                />
                {isAvailable ? "Online" : "Offline"}
              </span>
              <span className="truncate">{email}</span>
            </div>
          </div>

          {/* Play a game — opens a small picker menu */}
          <div className="relative shrink-0">
            <button
              onClick={() => {
                setGameMenuOpen((o) => !o);
                sound.tap();
              }}
              title="Play a game"
              className={`p-2 rounded-full transition-colors cursor-pointer ${
                gameMenuOpen
                  ? "bg-primary/10 text-primary"
                  : "text-gray-500 hover:bg-primary/10 hover:text-primary"
              }`}
            >
              <VideogameAssetIcon fontSize="small" />
            </button>

            {gameMenuOpen && (
              <>
                {/* click-away backdrop */}
                <div
                  className="fixed inset-0 z-10"
                  onClick={() => setGameMenuOpen(false)}
                />
                <div className="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-20 overflow-hidden animate-pop-in">
                  <button
                    onClick={() => {
                      setActiveGame("monopoly");
                      setGameMenuOpen(false);
                      sound.tap();
                    }}
                    className="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer"
                  >
                    <CasinoIcon fontSize="small" className="text-primary" />
                    Monopoly
                  </button>
                  <button
                    onClick={() => {
                      setActiveGame("rps");
                      setGameMenuOpen(false);
                      sound.tap();
                    }}
                    className="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer border-t border-gray-100"
                  >
                    <SportsMmaIcon fontSize="small" className="text-primary" />
                    Rock · Paper · Scissors
                  </button>
                </div>
              </>
            )}
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
          <div className="flex items-center gap-3 px-4 sm:px-8 py-3 bg-red-50 border-t border-red-100 animate-pop-in">
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
      <div className="relative flex-1 min-h-0">
        <div
          ref={scrollRef}
          onScroll={onScroll}
          className="absolute inset-0 overflow-y-auto px-2 sm:px-4 py-3 bg-white"
        >
          {allMessages.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center text-gray-400 text-sm gap-2">
              <span className="text-3xl">👋</span>
              <span>No messages yet — say hi to {name.split(" ")[0]}</span>
            </div>
          ) : (
            allMessages.map((msg, i) => {
              const isSent = msg.senderId === myUid;
              const prev = allMessages[i - 1];
              const next = allMessages[i + 1];
              const newDay =
                !prev || dayKey(prev.createdAt) !== dayKey(msg.createdAt);
              const firstInGroup =
                newDay ||
                prev.senderId !== msg.senderId ||
                msg.createdAt - prev.createdAt > GROUP_GAP_MS;
              const lastInGroup =
                !next ||
                next.senderId !== msg.senderId ||
                dayKey(next.createdAt) !== dayKey(msg.createdAt) ||
                next.createdAt - msg.createdAt > GROUP_GAP_MS;
              const active = activeMsgId === msg.id;

              // Bubble corners: fully rounded, with a small "tail" on the last
              // bubble of a run (bottom-right for me, bottom-left for them).
              const radius = isSent
                ? `rounded-2xl ${lastInGroup ? "rounded-br-sm" : ""}`
                : `rounded-2xl ${lastInGroup ? "rounded-bl-sm" : ""}`;

              return (
                <Fragment key={msg.id}>
                  {newDay && (
                    <div className="flex justify-center my-3">
                      <span className="px-3 py-1 rounded-full bg-gray-100 text-[11px] text-gray-500">
                        {formatDay(msg.createdAt)}
                      </span>
                    </div>
                  )}
                  <div
                    className={`group flex items-end gap-1.5 animate-msg-in ${
                      isSent ? "justify-end" : "justify-start"
                    } ${firstInGroup ? "mt-3" : "mt-0.5"}`}
                  >
                    {/* Delete (own messages only): fades in on hover (desktop)
                        or when the bubble is tapped (touch — no hover). */}
                    {isSent && (
                      <button
                        onClick={() => deleteMessage(msg.id)}
                        title="Delete message"
                        className={`${
                          active ? "opacity-100 scale-100" : "opacity-0 scale-90"
                        } group-hover:opacity-100 group-hover:scale-100 transition-all p-1.5 rounded-full bg-gray-100 text-gray-400 hover:bg-red-100 hover:text-red-600 cursor-pointer shrink-0`}
                      >
                        <DeleteOutlineIcon style={{ fontSize: 16 }} />
                      </button>
                    )}
                    <div className="flex flex-col max-w-[78%] sm:max-w-sm">
                      <div
                        onClick={
                          isSent
                            ? () => {
                                setActiveMsgId((cur) =>
                                  cur === msg.id ? null : msg.id
                                );
                                sound.tap();
                              }
                            : undefined
                        }
                        className={`px-3.5 py-2 text-sm shadow-sm whitespace-pre-wrap break-words ${radius} ${
                          isSent
                            ? "bg-primary text-white cursor-pointer"
                            : "bg-light-bg text-gray-800"
                        }`}
                      >
                        {msg.text}
                      </div>
                      {lastInGroup && (
                        <span
                          className={`mt-0.5 px-1 text-[10px] text-gray-400 ${
                            isSent ? "text-right" : "text-left"
                          }`}
                        >
                          {formatTime(msg.createdAt)}
                        </span>
                      )}
                    </div>
                  </div>
                </Fragment>
              );
            })
          )}
          <div ref={bottomRef} />
        </div>

        {/* Floating jump-to-latest (shows when scrolled up) */}
        {!nearBottom && allMessages.length > 0 && (
          <button
            onClick={jumpToBottom}
            title="Jump to latest"
            className="animate-pop-in absolute bottom-4 right-4 h-10 w-10 flex items-center justify-center rounded-full bg-white border border-gray-200 shadow-md text-gray-600 hover:text-primary hover:border-primary/40 transition-colors cursor-pointer"
          >
            <KeyboardArrowDownIcon />
            {hasNewBelow && (
              <span className="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full bg-primary border-2 border-white" />
            )}
          </button>
        )}
      </div>

      {/* "almost at the limit" hint + "wait for reply" banner */}
      {!isBlocked && remaining === 1 && (
        <div className="shrink-0 px-4 py-1.5 bg-amber-50/70 text-amber-700 text-[11px] text-center border-t border-amber-100">
          1 more message, then wait for {name.split(" ")[0]} to reply.
        </div>
      )}
      {isBlocked && (
        <div className="shrink-0 px-4 py-2 bg-amber-50 text-amber-700 text-xs text-center border-t border-amber-100">
          You've sent {MAX_MESSAGES} messages. Wait for {name.split(" ")[0]} to
          reply before sending more.
        </div>
      )}

      {/* Input */}
      <div className="shrink-0 p-3 bg-light-bg border-t border-gray-200">
        <form onSubmit={sendMessage} className="flex items-center gap-2">
          <div
            className={`flex-1 flex items-center bg-white h-12 rounded-2xl border px-4 transition-all ${
              isBlocked
                ? "border-gray-200 opacity-60"
                : "border-[#ddd] focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
            }`}
          >
            <input
              type="text"
              value={message}
              disabled={isBlocked || sending}
              placeholder={
                isBlocked ? "Waiting for a reply..." : "Type a message..."
              }
              className="flex-1 text-sm bg-transparent focus:outline-none disabled:cursor-not-allowed"
              onChange={(e) => setMessage(e.target.value)}
              onFocus={() =>
                setTimeout(
                  () =>
                    bottomRef.current?.scrollIntoView({ behavior: "smooth" }),
                  300
                )
              }
            />
          </div>
          <button
            type="submit"
            disabled={isBlocked || sending || !message.trim()}
            title="Send"
            className="h-12 w-12 shrink-0 flex items-center justify-center rounded-full bg-primary text-white shadow-sm hover:scale-105 active:scale-95 transition-transform disabled:opacity-40 disabled:hover:scale-100 disabled:cursor-not-allowed cursor-pointer"
          >
            <SendIcon fontSize="small" />
          </button>
        </form>
      </div>
    </div>
  );
}
