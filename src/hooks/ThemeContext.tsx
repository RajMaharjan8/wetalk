import { createContext, useEffect, useState } from "react";
import { onAuthStateChanged, signOut, type User } from "firebase/auth";
import { doc, getDoc, setDoc } from "firebase/firestore";
import { auth, db } from "../firebase";

export const ThemeContext = createContext<any>(null);

interface ThemeContextProps {
  children: React.ReactNode;
}

export default function ThemeContextProvider({ children }: ThemeContextProps) {
  // currentUser = the logged-in Firebase user (or null if logged out)
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  // loading = true while Firebase is still checking who's logged in
  const [loading, setLoading] = useState(true);
  // banned = true when the last login attempt was rejected because the admin
  // banned this account. The Login page reads it to show a message.
  const [banned, setBanned] = useState(false);

  useEffect(() => {
    // Firebase calls this callback automatically:
    //  - once on page load (to restore an existing session)
    //  - every time someone logs in or out
    const unsubscribe = onAuthStateChanged(auth, async (user) => {
      if (user) {
        // If the admin banned this account, kick them straight back out.
        const snap = await getDoc(doc(db, "users", user.uid));
        if (snap.exists() && snap.data().banned) {
          setBanned(true);
          setCurrentUser(null);
          setLoading(false);
          await signOut(auth); // fires this callback again with user = null
          return;
        }

        // Save / refresh this user's full profile on every session so the
        // "users" collection always has their name, email, photo and online
        // flag. (A user the admin DELETED is recreated here on next sign-in.)
        await setDoc(
          doc(db, "users", user.uid),
          {
            uid: user.uid,
            name: user.displayName,
            email: user.email,
            photoURL: user.photoURL,
            online: true,
          },
          { merge: true }
        );
      }
      setCurrentUser(user);
      setLoading(false);
    });

    // Cleanup: stop listening when the app unmounts
    return () => unsubscribe();
  }, []);

  // Best-effort: mark offline when the tab/window is closed.
  // (Not 100% guaranteed — browsers may kill the tab before the write
  // finishes. True presence would need Realtime Database's onDisconnect.)
  useEffect(() => {
    const handleClose = () => {
      const uid = auth.currentUser?.uid;
      if (uid) {
        setDoc(doc(db, "users", uid), { online: false }, { merge: true });
      }
    };
    window.addEventListener("beforeunload", handleClose);
    return () => window.removeEventListener("beforeunload", handleClose);
  }, []);

  // Logout helper any component can call.
  // We update "online: false" as best-effort (fire-and-forget) so a slow or
  // blocked Firestore write can NEVER stop the actual sign-out from happening.
  const logout = async () => {
    const uid = auth.currentUser?.uid;
    if (uid) {
      setDoc(doc(db, "users", uid), { online: false }, { merge: true }).catch(
        (error) => console.error("Could not set offline:", error)
      );
    }
    // This clears Firebase's stored session and fires onAuthStateChanged(null),
    // which sets token to null and sends you back to /login automatically.
    await signOut(auth);
  };

  const value = {
    currentUser,
    // token is what your routes check. uid exists only when logged in.
    token: currentUser ? currentUser.uid : null,
    loading,
    logout,
    // banned = the last sign-in was blocked by an admin ban.
    banned,
    // clearBanned lets the Login page reset the message before a new attempt.
    clearBanned: () => setBanned(false),
  };

  // Wait until Firebase has answered before showing routes,
  // otherwise a refresh briefly looks "logged out" and kicks you to /login.
  if (loading) {
    return (
      <div className="h-screen w-full flex justify-center items-center">
        Loading...
      </div>
    );
  }

  return <ThemeContext value={value}>{children}</ThemeContext>;
}
