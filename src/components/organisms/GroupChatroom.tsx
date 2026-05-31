import SendIcon from "@mui/icons-material/Send";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import GroupsIcon from "@mui/icons-material/Groups";
import LogoutIcon from "@mui/icons-material/Logout";
import GroupInfoModal from "./GroupInfoModal";
import { useContext, useEffect, useRef, useState } from "react";
import {
  addDoc,
  arrayRemove,
  collection,
  deleteDoc,
  doc,
  getDocs,
  onSnapshot,
  setDoc,
  updateDoc,
} from "firebase/firestore";
import { db } from "../../firebase";
import { ThemeContext } from "../../hooks/ThemeContext";
import { notifyRecipients } from "../../notifications";

export interface Group {
  id: string;
  name: string;
  members: string[];
  createdBy?: string;
}

interface GroupUser {
  uid: string;
  name: string;
  email: string;
  photoURL?: string;
}

interface GroupChatroomProps {
  group: Group;
  allUsers: GroupUser[]; // to resolve member names + offer people to add
  onBack: () => void;
  onLeft: () => void; // called after I leave, so the parent clears selection
}

interface GroupMessage {
  id: string;
  text: string;
  senderId: string;
  senderName: string;
  senderPhotoURL?: string;
  createdAt: number;
}

// Groups keep more history than 1-on-1 chats (which keep only 4).
const MAX_MESSAGES = 50;

function toMillis(value: unknown): number {
  if (typeof value === "number") return value;
  if (value && typeof (value as { toMillis?: () => number }).toMillis === "function") {
    return (value as { toMillis: () => number }).toMillis();
  }
  return 0;
}

export default function GroupChatroom({
  group,
  allUsers,
  onBack,
  onLeft,
}: GroupChatroomProps) {
  const { currentUser } = useContext(ThemeContext);
  const myUid: string = currentUser.uid;

  const [messages, setMessages] = useState<GroupMessage[]>([]);
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);
  const [confirmingLeave, setConfirmingLeave] = useState(false);
  const [showInfo, setShowInfo] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  // Real-time messages, sorted in JS so mixed time formats still work.
  useEffect(() => {
    const messagesRef = collection(db, "groups", group.id, "messages");
    const unsubscribe = onSnapshot(messagesRef, (snapshot) => {
      const msgs: GroupMessage[] = snapshot.docs.map((d) => {
        const data = d.data();
        return {
          id: d.id,
          text: data.text,
          senderId: data.senderId,
          senderName: data.senderName ?? "Someone",
          senderPhotoURL: data.senderPhotoURL,
          createdAt: toMillis(data.createdAt),
        };
      });
      msgs.sort((a, b) => a.createdAt - b.createdAt);
      setMessages(msgs.slice(-MAX_MESSAGES));
    });
    return () => unsubscribe();
  }, [group.id]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const sendMessage = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const text = message.trim();
    if (!text || sending) return;

    setSending(true);
    setMessage("");
    try {
      const now = Date.now();
      const messagesRef = collection(db, "groups", group.id, "messages");

      // message write + group summary write in parallel (faster notify)
      await Promise.all([
        addDoc(messagesRef, {
          text,
          senderId: myUid,
          senderName: currentUser.displayName ?? "Someone",
          senderPhotoURL: currentUser.photoURL ?? null,
          createdAt: now,
        }),
        setDoc(
          doc(db, "groups", group.id),
          {
            lastMessage: text,
            lastSenderId: myUid,
            lastSenderName: currentUser.displayName ?? "Someone",
            lastMessageAt: now,
          },
          { merge: true }
        ),
      ]);

      // Push a notification to every other member (works even if their app is
      // closed). Fire-and-forget — never blocks sending.
      notifyRecipients(
        group.members.filter((m) => m !== myUid),
        {
          title: group.name,
          body: `${currentUser.displayName ?? "Someone"}: ${text}`,
          icon: currentUser.photoURL ?? "",
          tag: group.id,
        }
      );

      // Trim to the newest MAX_MESSAGES (delete the oldest beyond that).
      const allDocs = await getDocs(messagesRef);
      const sorted = allDocs.docs
        .map((d) => ({ ref: d.ref, at: toMillis(d.data().createdAt) }))
        .sort((a, b) => a.at - b.at);
      const extra = sorted.slice(0, Math.max(0, sorted.length - MAX_MESSAGES));
      await Promise.all(extra.map((x) => deleteDoc(x.ref)));
    } catch (error) {
      console.error("Could not send group message:", error);
    } finally {
      setSending(false);
    }
  };

  // Leave the group: remove me from members. If I'm the last one, delete it.
  const leaveGroup = async () => {
    setConfirmingLeave(false);
    try {
      const remaining = group.members.filter((m) => m !== myUid);
      if (remaining.length === 0) {
        // last one out — delete messages + the group doc
        const messagesRef = collection(db, "groups", group.id, "messages");
        const snap = await getDocs(messagesRef);
        await Promise.all(snap.docs.map((d) => deleteDoc(d.ref)));
        await deleteDoc(doc(db, "groups", group.id));
      } else {
        await updateDoc(doc(db, "groups", group.id), {
          members: arrayRemove(myUid),
        });
      }
      onLeft();
    } catch (error) {
      console.error("Could not leave group:", error);
    }
  };

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

          {/* avatar + name open the group info / management panel */}
          <button
            onClick={() => setShowInfo(true)}
            title="Group info"
            className="flex items-center gap-3 min-w-0 flex-1 text-left cursor-pointer"
          >
            <div className="h-12 w-12 shrink-0 rounded-full bg-primary flex items-center justify-center text-white">
              <GroupsIcon />
            </div>
            <div className="text-gray-600 min-w-0 flex-1">
              <h3 className="font-semibold truncate">{group.name}</h3>
              <span className="font-light text-xs sm:text-sm">
                {group.members.length}{" "}
                {group.members.length === 1 ? "member" : "members"} · View info
              </span>
            </div>
          </button>

          {/* Leave group */}
          <button
            onClick={() => setConfirmingLeave(true)}
            title="Leave group"
            className="p-2 rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer shrink-0"
          >
            <LogoutIcon fontSize="small" />
          </button>
        </div>

        {/* In-app leave confirmation */}
        {confirmingLeave && (
          <div className="flex items-center gap-3 px-4 sm:px-8 py-3 bg-red-50 border-t border-red-100">
            <span className="text-sm text-red-700 flex-1">
              Leave "{group.name}"? You'll stop receiving its messages.
            </span>
            <button
              onClick={() => setConfirmingLeave(false)}
              className="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-white transition-colors cursor-pointer shrink-0"
            >
              Cancel
            </button>
            <button
              onClick={leaveGroup}
              className="px-3 py-1.5 rounded-lg text-sm bg-red-600 text-white hover:bg-red-700 transition-colors cursor-pointer shrink-0"
            >
              Leave
            </button>
          </div>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-2 py-2 bg-white">
        {messages.length === 0 ? (
          <div className="h-full flex items-center justify-center text-gray-400 text-sm">
            No messages yet — say hi 👋
          </div>
        ) : (
          messages.map((msg) => {
            const isSent = msg.senderId === myUid;
            const initials = msg.senderName
              .split(" ")
              .map((w) => w[0])
              .join("")
              .toUpperCase();
            return (
              <div
                key={msg.id}
                className={`flex items-end gap-2 ${
                  isSent ? "justify-end" : "justify-start"
                }`}
              >
                {/* sender avatar (only for other people's messages) */}
                {!isSent && (
                  <div className="h-7 w-7 rounded-full bg-primary overflow-hidden flex items-center justify-center text-white text-[10px] shrink-0 mb-1.5">
                    {msg.senderPhotoURL ? (
                      <img
                        src={msg.senderPhotoURL}
                        alt={msg.senderName}
                        referrerPolicy="no-referrer"
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      initials
                    )}
                  </div>
                )}
                <div
                  className={`max-w-[75%] sm:max-w-xs px-4 py-2 rounded-2xl m-1.5 text-sm shadow-sm ${
                    isSent
                      ? "bg-primary text-white rounded-br-sm"
                      : "bg-light-bg text-gray-800 rounded-bl-sm"
                  }`}
                >
                  {/* sender name above other people's messages */}
                  {!isSent && (
                    <p className="text-[11px] font-semibold text-primary mb-0.5">
                      {msg.senderName}
                    </p>
                  )}
                  <p className="wrap-break-word">{msg.text}</p>
                </div>
              </div>
            );
          })
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="shrink-0 p-3 bg-light-bg border-t border-gray-200">
        <form
          onSubmit={sendMessage}
          className="flex items-center bg-white w-full h-12 border border-[#ddd] rounded-2xl overflow-hidden outline-primary has-[input:focus-within]:outline-2"
        >
          <input
            type="text"
            value={message}
            disabled={sending}
            placeholder="Type a message..."
            className="focus:outline-none flex-1 ml-4 text-sm disabled:cursor-not-allowed bg-transparent"
            onChange={(e) => setMessage(e.target.value)}
          />
          <button
            type="submit"
            disabled={sending || !message.trim()}
            className="px-4 sm:px-6 py-2 h-full flex gap-2 justify-center items-center bg-primary text-white hover:text-primary hover:bg-white transition-all ease-in-out cursor-pointer shrink-0 text-sm disabled:opacity-50 disabled:hover:bg-primary disabled:hover:text-white disabled:cursor-not-allowed"
          >
            <span className="hidden sm:inline">Send</span>
            <SendIcon fontSize="small" />
          </button>
        </form>
      </div>

      {/* Group info / management panel */}
      {showInfo && (
        <GroupInfoModal
          group={group}
          allUsers={allUsers}
          myUid={myUid}
          onClose={() => setShowInfo(false)}
        />
      )}
    </div>
  );
}
