import { useEffect, useState } from "react";
import {
  collection,
  deleteDoc,
  doc,
  onSnapshot,
  updateDoc,
} from "firebase/firestore";
import { db } from "../firebase";

// Hardcoded admin credentials (simple username/password gate — no Google/Gmail).
const ADMIN_USERNAME = "admin";
const ADMIN_PASSWORD = "blondehairadmin";
// Remembers the admin session for this browser tab so a refresh doesn't log out.
const ADMIN_SESSION_KEY = "admin-authed";

interface ManagedUser {
  uid: string;
  name?: string;
  email?: string;
  photoURL?: string;
  online?: boolean;
  banned?: boolean;
}

export default function Admin() {
  const [authed, setAuthed] = useState(
    () => sessionStorage.getItem(ADMIN_SESSION_KEY) === "true"
  );

  if (!authed) {
    return <AdminLogin onSuccess={() => setAuthed(true)} />;
  }
  return (
    <AdminDashboard
      onLogout={() => {
        sessionStorage.removeItem(ADMIN_SESSION_KEY);
        setAuthed(false);
      }}
    />
  );
}

// ---- Login form (username + password only) ----
function AdminLogin({ onSuccess }: { onSuccess: () => void }) {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (username === ADMIN_USERNAME && password === ADMIN_PASSWORD) {
      sessionStorage.setItem(ADMIN_SESSION_KEY, "true");
      setError("");
      onSuccess();
    } else {
      setError("Invalid username or password.");
    }
  };

  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-light-bg px-4">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm bg-white rounded-2xl shadow-xl border border-gray-100 p-8 flex flex-col gap-4"
      >
        <div className="mx-auto h-16 w-16 rounded-2xl bg-primary flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-primary/30">
          A
        </div>
        <h1 className="text-center text-2xl font-bold text-gray-800">
          Admin Login
        </h1>

        <div className="w-full border border-[#ddd] rounded-lg p-2 outline-primary has-[input:focus-within]:outline-2 bg-white">
          <input
            placeholder="Username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            className="w-full focus:outline-none"
          />
        </div>

        <div className="w-full border border-[#ddd] rounded-lg p-2 outline-primary has-[input:focus-within]:outline-2 bg-white">
          <input
            placeholder="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full focus:outline-none"
          />
        </div>

        {error && <p className="text-red-500 text-sm">{error}</p>}

        <button
          type="submit"
          className="w-full px-4 py-2 bg-primary text-white rounded-lg cursor-pointer hover:bg-black transition-all ease-in-out"
        >
          Log in
        </button>
      </form>
    </div>
  );
}

// ---- Dashboard: list every user with Ban/Unban and Delete ----
function AdminDashboard({ onLogout }: { onLogout: () => void }) {
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    const unsubscribe = onSnapshot(
      collection(db, "users"),
      (snapshot) => {
        setError("");
        setUsers(snapshot.docs.map((d) => d.data() as ManagedUser));
      },
      (err) => {
        // Usually means the relaxed Firestore rules haven't been published yet.
        console.error("Could not read users:", err.code, err.message);
        setError(
          "Couldn't load users. Make sure the updated Firestore rules are published (firebase deploy --only firestore:rules)."
        );
      }
    );
    return () => unsubscribe();
  }, []);

  const toggleBan = async (user: ManagedUser) => {
    setBusy(user.uid);
    try {
      await updateDoc(doc(db, "users", user.uid), { banned: !user.banned });
    } catch (err) {
      console.error("Could not update ban status:", err);
    } finally {
      setBusy(null);
    }
  };

  const deleteUser = async (user: ManagedUser) => {
    if (
      !confirm(
        `Delete ${user.name ?? "this user"} from the app? They can sign in again unless banned.`
      )
    )
      return;
    setBusy(user.uid);
    try {
      await deleteDoc(doc(db, "users", user.uid));
    } catch (err) {
      console.error("Could not delete user:", err);
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="min-h-screen w-full bg-light-bg">
      <header className="flex items-center justify-between bg-white border-b border-gray-200 px-6 py-4">
        <h1 className="text-xl font-bold text-gray-800">Admin · Users</h1>
        <button
          onClick={onLogout}
          className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
        >
          Log out
        </button>
      </header>

      <div className="max-w-3xl mx-auto p-4 sm:p-6">
        {error && (
          <p className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm font-medium text-red-600">
            {error}
          </p>
        )}
        {users.length === 0 ? (
          <p className="text-gray-400 text-sm py-8 text-center">
            No users yet.
          </p>
        ) : (
          <ul className="flex flex-col gap-2">
            {users.map((user) => (
              <li
                key={user.uid}
                className="flex items-center gap-3 bg-white rounded-xl border border-gray-100 px-4 py-3 shadow-sm"
              >
                <div className="h-11 w-11 rounded-full overflow-hidden bg-primary flex items-center justify-center text-white font-semibold shrink-0">
                  {user.photoURL ? (
                    <img
                      src={user.photoURL}
                      alt={user.name ?? ""}
                      referrerPolicy="no-referrer"
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    user.name?.[0]?.toUpperCase() ?? "?"
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-gray-800 truncate flex items-center gap-2">
                    {user.name ?? "Unknown"}
                    {user.banned && (
                      <span className="text-[10px] uppercase tracking-wide bg-red-100 text-red-600 px-2 py-0.5 rounded-full">
                        Banned
                      </span>
                    )}
                  </p>
                  <p className="text-xs text-gray-400 truncate">{user.email}</p>
                </div>

                <button
                  onClick={() => toggleBan(user)}
                  disabled={busy === user.uid}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors cursor-pointer disabled:opacity-50 ${
                    user.banned
                      ? "text-green-600 hover:bg-green-50"
                      : "text-amber-600 hover:bg-amber-50"
                  }`}
                >
                  {user.banned ? "Unban" : "Ban"}
                </button>
                <button
                  onClick={() => deleteUser(user)}
                  disabled={busy === user.uid}
                  className="px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors cursor-pointer disabled:opacity-50"
                >
                  Delete
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
