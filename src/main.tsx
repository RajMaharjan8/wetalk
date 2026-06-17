import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import GlobalRoutes from "./GlobalRoutes.tsx";
import ThemeContextProvider from "./hooks/ThemeContext.tsx";

// iOS PWA keyboard fix: in a standalone PWA, `100dvh` does NOT shrink when the
// on-screen keyboard opens, so a bottom-docked composer ends up hidden behind
// it. The visualViewport API *does* report the keyboard-reduced height, so we
// mirror it into a `--app-height` CSS variable the layout uses instead.
const setAppHeight = () => {
  const h = window.visualViewport?.height ?? window.innerHeight;
  document.documentElement.style.setProperty("--app-height", `${h}px`);
};
setAppHeight();
window.visualViewport?.addEventListener("resize", setAppHeight);
window.visualViewport?.addEventListener("scroll", setAppHeight);
window.addEventListener("resize", setAppHeight);
window.addEventListener("orientationchange", setAppHeight);

createRoot(document.getElementById("root")!).render(
  <StrictMode>

    <ThemeContextProvider>
      <GlobalRoutes />
    </ThemeContextProvider>
    
  </StrictMode>,
);
