import { createContext, useEffect, useState } from "react";
import { onAuthStateChanged, signOut, type User } from "firebase/auth";
import { doc, setDoc } from "firebase/firestore";
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

  useEffect(() => {
    // Firebase calls this callback automatically:
    //  - once on page load (to restore an existing session)
    //  - every time someone logs in or out
    const unsubscribe = onAuthStateChanged(auth, async (user) => {
      setCurrentUser(user);
      setLoading(false);
      // Mark this user as online whenever a session is active
      if (user) {
        await setDoc(
          doc(db, "users", user.uid),
          { online: true },
          { merge: true }
        );
      }
    });

    // Cleanup: stop listening when the app unmounts
    return () => unsubscribe();
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
