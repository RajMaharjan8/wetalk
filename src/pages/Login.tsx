import { useContext, useState } from "react";
import { ThemeContext } from "../hooks/ThemeContext";
import { Navigate } from "react-router-dom";
import { signInWithPopup } from "firebase/auth";
import { doc, setDoc } from "firebase/firestore";
import { auth, googleProvider, db } from "../firebase";

export default function Login() {
  const user = useContext(ThemeContext);
  const [loading, setLoading] = useState(false);

  if (user.token) {
    return <Navigate to="/" />;
  }

  // Runs when the user clicks "Continue with Google"
  const handleGoogleLogin = async () => {
    try {
      setLoading(true);
      const result = await signInWithPopup(auth, googleProvider);
      const loggedInUser = result.user;

      await setDoc(
        doc(db, "users", loggedInUser.uid),
        {
          uid: loggedInUser.uid,
          name: loggedInUser.displayName,
          email: loggedInUser.email,
          photoURL: loggedInUser.photoURL,
          online: true,
        },
        { merge: true }
      );
    } catch (error) {
      console.error("Google login failed:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-linear-to-br from-light-bg via-white to-light-bg px-4">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-xl border border-gray-100 p-8 sm:p-10 text-center">
        {/* Logo badge */}
        <div className="mx-auto h-16 w-16 rounded-2xl bg-primary flex items-center justify-center text-white text-2xl font-bold shadow-lg shadow-primary/30">
          W
        </div>

        <h1 className="mt-6 text-2xl font-bold text-gray-800">
          Welcome to WeTalk
        </h1>
        <p className="mt-2 text-sm text-gray-500">
          Sign in to start chatting with your friends in real time.
        </p>

        {/* Google sign-in button */}
        <button
          onClick={handleGoogleLogin}
          disabled={loading}
          className="mt-8 w-full h-12 flex items-center justify-center gap-3 rounded-xl border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 hover:shadow-md active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
        >
          {loading ? (
            <span className="h-5 w-5 border-2 border-gray-300 border-t-primary rounded-full animate-spin" />
          ) : (
            <svg className="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
              <path
                fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"
              />
              <path
                fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"
              />
              <path
                fill="#FBBC05"
                d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"
              />
              <path
                fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"
              />
            </svg>
          )}
          <span>{loading ? "Signing in..." : "Continue with Google"}</span>
        </button>

        <p className="mt-6 text-xs text-gray-400">
          By continuing, you agree to our Terms & Privacy Policy.
        </p>
      </div>
    </div>
  );
}
