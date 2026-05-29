import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import GlobalRoutes from "./GlobalRoutes.tsx";
import ThemeContextProvider from "./hooks/ThemeContext.tsx";

createRoot(document.getElementById("root")!).render(
  <StrictMode>

    <ThemeContextProvider>
      <GlobalRoutes />
    </ThemeContextProvider>
    
  </StrictMode>,
);
