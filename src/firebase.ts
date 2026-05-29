// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAuth } from "firebase/auth";
import { GoogleAuthProvider } from "firebase/auth";
import { getFirestore } from "firebase/firestore";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyBDJKGfBCn4Mdpb63zSLpDEtzynxQHmICs",
  authDomain: "wetalk-b1900.firebaseapp.com",
  projectId: "wetalk-b1900",
  storageBucket: "wetalk-b1900.firebasestorage.app",
  messagingSenderId: "917887753810",
  appId: "1:917887753810:web:493f1f6a1083c9ad78898b",
  measurementId: "G-Z56GS7Q8RG"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);
export const googleProvider = new GoogleAuthProvider();
export const db = getFirestore(app);
