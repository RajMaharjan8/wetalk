import "./App.css";
import SearchIcon from "@mui/icons-material/Search";
import LogoutIcon from "@mui/icons-material/Logout";
import UserTabs from "./components/molecules/UserTabs";
import Chatroom from "./components/organisms/Chatroom";
import { useContext, useEffect, useState } from "react";
import { collection, onSnapshot } from "firebase/firestore";
import { db } from "./firebase";
import { ThemeContext } from "./hooks/ThemeContext";

interface ChatUser {
  uid: string;
  name: string;
  email: string;
  photoURL?: string;
  online?: boolean;
}

function App() {
  const { currentUser, logout } = useContext(ThemeContext);

  const [users, setUsers] = useState<ChatUser[]>([]);
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

  // Everyone except me, filtered by the search box
  const otherUsers = users
    .filter((u) => u.uid !== currentUser?.uid)
    .filter((u) => u.name?.toLowerCase().includes(search.toLowerCase()));

  const activeUser = otherUsers.find((u) => u.uid === activeUid);

  return (
    <div className="grid grid-cols-1 lg:grid-cols-12 h-screen overflow-hidden">
      <div
        className={`lg:col-span-4 flex flex-col bg-light-bg h-full border-r border-gray-200 ${
          activeUser ? "hidden lg:flex" : "flex"
        }`}
      >
        {/* Current user + logout */}
        <div className="flex items-center gap-3 p-3 border-b border-gray-200">
          <div className="h-10 w-10 rounded-full overflow-hidden bg-primary flex items-center justify-center text-white shrink-0">
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
          <span className="font-semibold text-gray-700 truncate flex-1">
            {currentUser?.displayName}
          </span>
          <button
            onClick={logout}
            title="Logout"
            className="p-2 rounded-full hover:bg-light-text text-gray-600 cursor-pointer"
          >
            <LogoutIcon fontSize="small" />
          </button>
        </div>

        <div className="border-b-2 border-primary w-full p-3">
          <div className="bg-white border border-[#ddd] py-2 px-3 text-black flex gap-2 items-center rounded-xl outline-primary has-[input:focus-within]:outline-2">
            <SearchIcon className="text-[#c2c2c2] flex-shrink-0" fontSize="small" />
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
