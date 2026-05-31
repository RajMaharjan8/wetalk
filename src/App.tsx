import "./App.css";
import SearchIcon from "@mui/icons-material/Search";
import LogoutIcon from "@mui/icons-material/Logout";
import GroupAddIcon from "@mui/icons-material/GroupAdd";
import UserTabs from "./components/molecules/UserTabs";
import GroupTab from "./components/molecules/GroupTab";
import Chatroom from "./components/organisms/Chatroom";
import GroupChatroom from "./components/organisms/GroupChatroom";
import CreateGroupModal from "./components/organisms/CreateGroupModal";
import { useContext, useEffect, useRef, useState } from "react";
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
  lastSenderId?: string; // who sent the last message (for unread detection)
}

// A group the current user is a member of
interface GroupDoc {
  id: string;
  name: string;
  members: string[];
  createdBy?: string;
  lastMessage?: string;
  lastSenderId?: string;
  lastSenderName?: string;
  lastMessageAt?: number;
}

function App() {
  const { currentUser, logout } = useContext(ThemeContext);

  const [users, setUsers] = useState<ChatUser[]>([]);
  const [groups, setGroups] = useState<GroupDoc[]>([]);
  const [conversations, setConversations] = useState<
    Record<string, Conversation>
  >({});
  // Exactly one of these is set at a time (a direct chat OR a group).
  const [activeUid, setActiveUid] = useState<string | null>(null);
  const [activeGroupId, setActiveGroupId] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [showCreateGroup, setShowCreateGroup] = useState(false);

  // Unread tracking: remember the newest message time we've "seen" per chat or
  // group (persisted so it survives refreshes). A conversation is unread when
  // its last message is newer than that and was sent by the other person.
  const [lastSeen, setLastSeen] = useState<Record<string, number>>(() => {
    try {
      return JSON.parse(localStorage.getItem("wetalk-last-seen") || "{}");
    } catch {
      return {};
    }
  });
  const markSeen = (key: string, at: number) => {
    setLastSeen((prev) => {
      if ((prev[key] ?? 0) >= at) return prev;
      const next = { ...prev, [key]: at };
      localStorage.setItem("wetalk-last-seen", JSON.stringify(next));
      return next;
    });
  };

  // Refs let the listeners read the LATEST values without re-subscribing.
  const usersRef = useRef<ChatUser[]>([]);
  const activeUidRef = useRef<string | null>(null);
  const activeGroupIdRef = useRef<string | null>(null);
  usersRef.current = users;
  activeUidRef.current = activeUid;
  activeGroupIdRef.current = activeGroupId;

  // Remembers the newest message time we've already notified about, per chat
  // OR per group, so the same message never notifies twice.
  const notifiedAtRef = useRef<Record<string, number>>({});
  const notifyReadyRef = useRef(false); // skip the first direct-chat snapshot
  const groupNotifyReadyRef = useRef(false); // skip the first group snapshot

  // Selecting a direct chat clears any active group, and vice versa.
  const selectUser = (uid: string) => {
    setActiveGroupId(null);
    setActiveUid(uid);
    markSeen(uid, Date.now()); // opening a chat clears its unread state
  };
  const selectGroup = (id: string) => {
    setActiveUid(null);
    setActiveGroupId(id);
    markSeen(id, Date.now());
  };

  // Ask once for permission to show desktop notifications
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

  // Live list of MY conversations (direct chats I'm a participant of).
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
          lastSenderId?: string;
          lastMessageAt?: number;
        };
        const otherUid = data.participants.find((p) => p !== currentUser.uid);
        if (!otherUid || !data.lastMessage) return;

        const at = data.lastMessageAt ?? 0;
        map[otherUid] = {
          lastMessage: data.lastMessage,
          lastMessageAt: at,
          lastSenderId: data.lastSenderId,
        };

        // If I'm currently viewing this chat, keep it marked as seen.
        if (activeUidRef.current === otherUid) markSeen(otherUid, at);

        // ---- Notification (direct) ----
        const isIncoming = data.lastSenderId === otherUid;
        const isNew = at > (notifiedAtRef.current[d.id] ?? 0);
        const notLooking = document.hidden || activeUidRef.current !== otherUid;

        if (isIncoming && isNew && notifyReadyRef.current && notLooking) {
          if (
            "Notification" in window &&
            Notification.permission === "granted"
          ) {
            const sender = usersRef.current.find((u) => u.uid === otherUid);
            new Notification(`New message from ${sender?.name ?? "someone"}`, {
              body: data.lastMessage,
              icon: sender?.photoURL,
            });
          }
        }
        notifiedAtRef.current[d.id] = at;
      });
      notifyReadyRef.current = true;
      setConversations(map);
    });
    return () => unsubscribe();
  }, [currentUser]);

  // Live list of the groups I'm a member of
  useEffect(() => {
    if (!currentUser) return;
    const q = query(
      collection(db, "groups"),
      where("members", "array-contains", currentUser.uid)
    );
    const unsubscribe = onSnapshot(
      q,
      (snapshot) => {
      const list: GroupDoc[] = snapshot.docs.map((d) => ({
        id: d.id,
        ...(d.data() as Omit<GroupDoc, "id">),
      }));

      // ---- Notification (group) ----
      list.forEach((g) => {
        const at = g.lastMessageAt ?? 0;
        const isIncoming = !!g.lastSenderId && g.lastSenderId !== currentUser.uid;
        const isNew = at > (notifiedAtRef.current[g.id] ?? 0);
        const notLooking =
          document.hidden || activeGroupIdRef.current !== g.id;

        if (
          isIncoming &&
          isNew &&
          groupNotifyReadyRef.current &&
          notLooking &&
          g.lastMessage
        ) {
          if (
            "Notification" in window &&
            Notification.permission === "granted"
          ) {
            new Notification(g.name, {
              body: `${g.lastSenderName ?? "Someone"}: ${g.lastMessage}`,
            });
          }
        }
        notifiedAtRef.current[g.id] = at;
      });
      groupNotifyReadyRef.current = true;

      // newest-active groups first
      list.sort((a, b) => (b.lastMessageAt ?? 0) - (a.lastMessageAt ?? 0));
      setGroups(list);
      },
      (error) => {
        // Most likely the groups rules aren't published yet.
        console.error("Could not read groups:", error.code, error.message);
      }
    );
    return () => unsubscribe();
  }, [currentUser]);

  // True when the conversation's last message came from the other side and is
  // newer than what we've seen.
  const isUnread = (key: string, at?: number, senderId?: string) =>
    !!senderId &&
    senderId !== currentUser?.uid &&
    (at ?? 0) > (lastSeen[key] ?? 0);

  // Everyone except me, filtered by search, with conversation preview attached
  const otherUsers = users
    .filter((u) => u.uid !== currentUser?.uid)
    .filter((u) => u.name?.toLowerCase().includes(search.toLowerCase()))
    .map((u) => {
      const conversation = conversations[u.uid];
      return {
        ...u,
        conversation,
        unread: isUnread(
          u.uid,
          conversation?.lastMessageAt,
          conversation?.lastSenderId
        ),
      };
    })
    .sort(
      (a, b) =>
        (b.conversation?.lastMessageAt ?? 0) -
        (a.conversation?.lastMessageAt ?? 0)
    );

  const visibleGroups = groups
    .filter((g) => g.name?.toLowerCase().includes(search.toLowerCase()))
    .map((g) => ({
      ...g,
      unread: isUnread(g.id, g.lastMessageAt, g.lastSenderId),
    }));

  const activeUser = otherUsers.find((u) => u.uid === activeUid);
  const activeGroup = groups.find((g) => g.id === activeGroupId);
  const hasActivePane = !!activeUser || !!activeGroup;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 h-dvh overflow-hidden">
      <div
        className={`lg:col-span-4 flex flex-col bg-light-bg h-full border-r border-gray-200 ${
          hasActivePane ? "hidden lg:flex" : "flex"
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
              placeholder="Search names or groups..."
              className="w-full focus:outline-none text-sm"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto">
          {/* ---- Groups section ---- */}
          <div className="flex items-center justify-between px-4 pt-3 pb-1">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
              Groups
            </span>
            <button
              onClick={() => setShowCreateGroup(true)}
              title="New group"
              className="flex items-center gap-1 text-xs text-primary hover:text-secondary font-medium cursor-pointer"
            >
              <GroupAddIcon style={{ fontSize: 18 }} />
              New
            </button>
          </div>
          {visibleGroups.length === 0 ? (
            <p className="px-4 py-2 text-xs text-gray-400">
              No groups yet. Create one to chat with several people.
            </p>
          ) : (
            <ul className="flex flex-col w-full">
              {visibleGroups.map((g) => (
                <GroupTab
                  key={g.id}
                  name={g.name}
                  memberCount={g.members.length}
                  lastMessage={g.lastMessage}
                  lastSenderName={g.lastSenderName}
                  isActive={activeGroupId === g.id}
                  unread={g.unread}
                  onClick={() => selectGroup(g.id)}
                />
              ))}
            </ul>
          )}

          {/* ---- People section ---- */}
          <div className="px-4 pt-4 pb-1">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
              People
            </span>
          </div>
          <ul className="cursor-pointer flex flex-col w-full">
            {otherUsers.map((user) => (
              <UserTabs
                key={user.uid}
                name={user.name}
                email={user.email}
                photoURL={user.photoURL}
                isAvailable={!!user.online}
                lastMessage={user.conversation?.lastMessage}
                isActive={activeUid === user.uid}
                unread={user.unread}
                onClick={() => selectUser(user.uid)}
              />
            ))}
          </ul>
        </div>
      </div>

      <div
        className={`lg:col-span-8 h-full ${
          hasActivePane ? "flex" : "hidden lg:flex"
        }`}
      >
        {activeGroup ? (
          <GroupChatroom
            group={activeGroup}
            allUsers={users}
            onBack={() => setActiveGroupId(null)}
            onLeft={() => setActiveGroupId(null)}
          />
        ) : activeUser ? (
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

      {/* Create-group modal */}
      {showCreateGroup && (
        <CreateGroupModal
          users={otherUsers}
          myUid={currentUser.uid}
          onClose={() => setShowCreateGroup(false)}
          onCreated={(groupId) => {
            setShowCreateGroup(false);
            selectGroup(groupId);
          }}
        />
      )}
    </div>
  );
}

export default App;
