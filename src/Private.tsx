import type React from "react";
import { useContext } from "react";
import { Navigate } from "react-router-dom";
import { ThemeContext } from "./hooks/ThemeContext";
interface PrivateProps{
    children: React.ReactNode
}
export default function Private({
    children
}: PrivateProps){


const user = useContext(ThemeContext);

if(!user.token){
 return <Navigate to="/login"/>;
}

return children;

}

