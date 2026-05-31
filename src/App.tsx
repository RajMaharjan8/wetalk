import "./App.css";
import SearchIcon from "@mui/icons-material/Search";
import LogoutIcon from "@mui/icons-material/Logout";
import UserTabs from "./components/molecules/UserTabs";
import Chatroom from "./components/organisms/Chatroom";
import { useContext, useEffect, useState } from "react";
import { collection, onSnapshot, query, where } from "firebase/firestore";
import { db } from "./firebase";
import { ThemeContext } from "./hooks/ThemeContext";

interface ChatUser {
  uid: string;
  name: string;
  email: string;
  photoURL?: string;
  online?: boolean;
}

// A conversation summary, keyed by the OTHER person's uid
interface Conversation {
  lastMessage: string;
  lastMessageAt: number; // millis, used only for sorting
}

function App() {
  const { currentUser, logout } = useContext(ThemeContext);

  const [users, setUsers] = useState<ChatUser[]>([]);
  const [conversations, setConversations] = useState<
    Record<string, Conversation>
  >({});
  const [activeUid, setActiveUid] = useState<string | null>(null);
  const [search, setSearch] = useState("");

  // Ask once for permission to show desktop notifications (used in Phase 6)
  useEffect(() => {
    if ("Notification" in window && Notification.permission === "default") {
      Notification.requestPermission();
    }
  }, []);

  // Live list of every registered user, updates in real time
  useEffect(() => {
    const unsubscribe = onSnapshot(collection(db, "users"), (snapshot) => {
      const list = snapshot.docs.map((doc) => doc.data() as ChatUser);
      setUsers(list);
    });
    return () => unsubscribe();
  }, []);

  // Live list of MY conversations (chats I'm a participant of).
  // We turn it into a map: otherUserUid -> { lastMessage, lastMessageAt }
  useEffect(() => {
    if (!currentUser) return;
    const q = query(
      collection(db, "chats"),
      where("participants", "array-contains", currentUser.uid)
    );
    const unsubscribe = onSnapshot(q, (snapshot) => {
      const map: Record<string, Conversation> = {};
      snapshot.docs.forEach((d) => {
        const data = d.data() as {
          participants: string[];
          lastMessage?: string;
          lastMessageAt?: { toMillis: () => number };
        };
        // the other participant is whoever isn't me
        const otherUid = data.participants.find((p) => p !== currentUser.uid);
        if (otherUid && data.lastMessage) {
          map[otherUid] = {
            lastMessage: data.lastMessage,
            lastMessageAt: data.lastMessageAt?.toMillis() ?? 0,
          };
        }
      });
      setConversations(map);
    });
    return () => unsubscribe();
  }, [currentUser]);

  // Everyone except me, filtered by the search box, with their
  // conversation preview attached (undefined if we've never chatted)
  const otherUsers = users
    .filter((u) => u.uid !== currentUser?.uid)
    .filter((u) => u.name?.toLowerCase().includes(search.toLowerCase()))
    .map((u) => ({ ...u, conversation: conversations[u.uid] }))
    // people you've chatted with first (most recent on top), then the rest
    .sort(
      (a, b) =>
        (b.conversation?.lastMessageAt ?? 0) -
        (a.conversation?.lastMessageAt ?? 0)
    );

  const activeUser = otherUsers.find((u) => u.uid === activeUid);

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 h-screen overflow-hidden">
      <div
        className={`lg:col-span-4 flex flex-col bg-light-bg h-full border-r border-gray-200 ${
          activeUser ? "hidden lg:flex" : "flex"
        }`}
      >
        {/* Current user + logout */}
        <div className="flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-200">
          <div className="h-11 w-11 rounded-full overflow-hidden bg-primary flex items-center justify-center text-white font-semibold ring-2 ring-primary/20 shrink-0">
            {currentUser?.photoURL ? (
              <img
                src={currentUser.photoURL}
                alt={currentUser.displayName ?? ""}
                referrerPolicy="no-referrer"
                className="h-full w-full object-cover"
              />
            ) : (
              currentUser?.displayName?.[0]?.toUpperCase()
            )}
          </div>
          <div className="flex-1 min-w-0">
            <h2 className="font-semibold text-gray-800 truncate leading-tight">
              {currentUser?.displayName}
            </h2>
            <span className="flex items-center gap-1.5 text-xs text-green-600">
              <span className="h-2 w-2 rounded-full bg-green-500" />
              Online
            </span>
          </div>
          <button
            onClick={logout}
            title="Logout"
            className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
          >
            <LogoutIcon fontSize="small" />
            <span className="hidden sm:inline">Logout</span>
          </button>
        </div>

        <div className="border-b-2 border-primary w-full p-3">
          <div className="bg-white border border-[#ddd] py-2 px-3 text-black flex gap-2 items-center rounded-xl outline-primary has-[input:focus-within]:outline-2">
            <SearchIcon className="text-[#c2c2c2] shrink-0" fontSize="small" />
            <input
              name="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search Names..."
              className="w-full focus:outline-none text-sm"
            />
          </div>
        </div>

        <ul className="cursor-pointer flex flex-col w-full overflow-y-auto flex-1">
          {otherUsers.map((user) => (
            <UserTabs
              key={user.uid}
              name={user.name}
              email={user.email}
              photoURL={user.photoURL}
              isAvailable={!!user.online}
              lastMessage={user.conversation?.lastMessage}
              isActive={activeUid === user.uid}
              onClick={() => setActiveUid(user.uid)}
            />
          ))}
        </ul>
      </div>

      <div
        className={`lg:col-span-8 h-full ${
          activeUser ? "flex" : "hidden lg:flex"
        }`}
      >
        {activeUser ? (
          <Chatroom
            uid={activeUser.uid}
            name={activeUser.name}
            email={activeUser.email}
            photoURL={activeUser.photoURL}
            isAvailable={!!activeUser.online}
            onBack={() => setActiveUid(null)}
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-white text-gray-400 text-base">
            Select a conversation to start chatting
          </div>
        )}
      </div>
    </div>
  );
}

export default App;
