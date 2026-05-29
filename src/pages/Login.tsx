import { useContext } from "react";
import { ThemeContext } from "../hooks/ThemeContext";
import { Navigate } from "react-router-dom";
import CustomButton from "../components/atoms/CustomButton";
import { signInWithPopup } from "firebase/auth";
import { doc, setDoc } from "firebase/firestore";
import { auth, googleProvider, db } from "../firebase";

export default function Login() {
  const user = useContext(ThemeContext);
  if (user.token) {
    return <Navigate to="/" />;
  }

  // Runs when the user clicks "Continue with Google"
  const handleGoogleLogin = async () => {
    try {
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

      console.log("Logged in & saved to Firestore:", loggedInUser);
    } catch (error) {
      console.error("Google login failed:", error);
    }
  };

  return (
    <div className="h-screen w-full flex justify-center items-center">
      <div className="border border-[#ddd] p-6 rounded-lg w-1/2 text-center">
        <h2 className="text-xl font-semibold mb-6">Welcome to WeTalk</h2>
        <CustomButton
          title="Continue with Google"
          onClickFunction={handleGoogleLogin}
        />
      </div>
    </div>
  );
}
