import { createBrowserRouter, RouterProvider } from "react-router-dom";
import App from "./App";
import Login from "./pages/Login";
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
]);

export default function GlobalRoutes() {
  return <RouterProvider router={routes} />;
}
