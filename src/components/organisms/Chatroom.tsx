import SendIcon from "@mui/icons-material/Send";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import { useContext, useEffect, useRef, useState } from "react";
import {
  addDoc,
  collection,
  deleteDoc,
  getDocs,
  limit,
  onSnapshot,
  orderBy,
  query,
  serverTimestamp,
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

  // Used by Phase 6 to detect genuinely new incoming messages
  const seenIds = useRef<Set<string>>(new Set());
  const initialized = useRef(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  // PHASE 4 + 6: listen to the latest 4 messages in real time
  useEffect(() => {
    // reset detection state when switching to a different conversation
    seenIds.current = new Set();
    initialized.current = false;

    const messagesRef = collection(db, "chats", chatId, "messages");
    // newest 4, then reversed so oldest-of-the-4 shows on top
    const q = query(messagesRef, orderBy("createdAt", "desc"), limit(4));

    const unsubscribe = onSnapshot(q, (snapshot) => {
      const msgs = snapshot.docs
        .map((d) => ({ id: d.id, ...(d.data() as Omit<Message, "id">) }))
        .reverse();

      // PHASE 6: notify on new messages from the other person
      msgs.forEach((m) => {
        if (!seenIds.current.has(m.id)) {
          seenIds.current.add(m.id);
          const isIncoming = m.senderId !== myUid;
          // skip the first batch (existing history) and only notify
          // when the tab isn't focused
          if (
            initialized.current &&
            isIncoming &&
            document.hidden &&
            "Notification" in window &&
            Notification.permission === "granted"
          ) {
            new Notification(`New message from ${name}`, {
              body: m.text,
              icon: photoURL,
            });
          }
        }
      });
      initialized.current = true;

      setAllMessages(msgs);
    });

    return () => unsubscribe();
  }, [chatId, myUid, name, photoURL]);

  // auto-scroll to the newest message
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [allMessages]);

  // PHASE 4: send a message, then PHASE 5: trim to the latest 4
  const sendMessage = async (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    const text = message.trim();
    if (!text) return;
    setMessage("");

    const messagesRef = collection(db, "chats", chatId, "messages");
    await addDoc(messagesRef, {
      text,
      senderId: myUid,
      createdAt: serverTimestamp(),
    });

    // PHASE 5: keep only the newest 4 — delete anything older
    const allDocs = await getDocs(
      query(messagesRef, orderBy("createdAt", "desc"))
    );
    const extra = allDocs.docs.slice(4); // everything past the newest 4
    await Promise.all(extra.map((d) => deleteDoc(d.ref)));
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

          <div className="h-12 w-12 aspect-square bg-primary rounded-full flex justify-center items-center relative text-white shrink-0 text-sm overflow-hidden">
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
            {isAvailable ? (
              <div className="h-3.5 w-3.5 bg-green-600 rounded-full border-2 border-white absolute bottom-0 right-0"></div>
            ) : null}
          </div>

          <div className="text-gray-600 min-w-0">
            <h3 className="font-semibold truncate">{name}</h3>
            <div className="flex gap-2 font-light text-xs sm:text-sm">
              <span className="shrink-0">{isAvailable ? "Online" : "Offline"}</span>
              <span className="truncate">{email}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-2 py-2">
        {allMessages.map((msg) => {
          const isSent = msg.senderId === myUid;
          return (
            <div key={msg.id}>
              {isSent ? (
                <div className="flex justify-end">
                  <div className="max-w-[75%] sm:max-w-xs p-3 text-white bg-primary rounded-lg m-2 font-light text-sm">
                    <p className="wrap-break-word">{msg.text}</p>
                  </div>
                </div>
              ) : (
                <div className="flex justify-start">
                  <div className="max-w-[75%] sm:max-w-xs p-3 text-black bg-light-bg rounded-lg m-2 font-light text-sm">
                    <p className="wrap-break-word">{msg.text}</p>
                  </div>
                </div>
              )}
            </div>
          );
        })}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="shrink-0 p-3 bg-light-bg border-t border-gray-200">
        <div className="flex items-center bg-white w-full h-12 border border-[#ddd] rounded-2xl overflow-hidden outline-primary has-[input:focus-within]:outline-2">
          <input
            type="text"
            value={message}
            placeholder="Type a message..."
            className="focus:outline-none flex-1 ml-4 text-sm"
            onChange={(e) => setMessage(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") e.currentTarget.form?.requestSubmit();
            }}
          />
          <button
            type="submit"
            onClick={sendMessage}
            className="px-4 sm:px-6 py-2 h-full flex gap-2 justify-center items-center bg-primary text-white hover:text-primary hover:bg-white transition-all ease-in-out cursor-pointer shrink-0 text-sm"
          >
            <span className="hidden sm:inline">Send</span>
            <SendIcon fontSize="small" />
          </button>
        </div>
      </div>
    </div>
  );
}
