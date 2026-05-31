import { createBrowserRouter, RouterProvider } from "react-router-dom";
import App from "./App";
import Login from "./pages/Login";
import Admin from "./pages/Admin";
import Private from "./Private";

const routes = createBrowserRouter([
  {
    path: "/",
    element: <Private><App /></Private>,
  },
  {
    path: "/login",
    element: <Login />,
  },
  {
    // Standalone admin panel — its own username/password gate, no Google login.
    path: "/admin",
    element: <Admin />,
  },
]);

export default function GlobalRoutes() {
  return <RouterProvider router={routes} />;
}
