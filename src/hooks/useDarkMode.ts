import { useEffect, useState } from "react";

const KEY = "wetalk-dark-mode";

// Read the saved preference once (defaults to light if never set).
function initialDark(): boolean {
  try {
    return localStorage.getItem(KEY) === "1";
  } catch {
    return false;
  }
}

// Manual dark-mode toggle, remembered in localStorage. Toggles the `dark` class
// on <html>, which the `dark:` Tailwind utilities key off.
export function useDarkMode(): [boolean, () => void] {
  const [dark, setDark] = useState(initialDark);

  useEffect(() => {
    const root = document.documentElement;
    root.classList.toggle("dark", dark);
    try {
      localStorage.setItem(KEY, dark ? "1" : "0");
    } catch {
      /* storage unavailable — ignore */
    }
  }, [dark]);

  return [dark, () => setDark((d) => !d)];
}
