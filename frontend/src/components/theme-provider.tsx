"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import {
  applyThemeClass,
  getCachedUiPreferences,
  updateUiPreferences,
  type UiTheme,
} from "@/lib/ui-preferences";

type ThemeContextValue = {
  theme: UiTheme;
  resolved: "light" | "dark";
  /** Apply theme locally (and to <html>) without persisting. */
  hydrateTheme: (theme: UiTheme) => void;
  /** Apply theme and persist via UI preferences API. */
  setTheme: (theme: UiTheme) => Promise<void>;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

function resolveNow(theme: UiTheme): "light" | "dark" {
  if (theme === "light" || theme === "dark") return theme;
  if (typeof window === "undefined") return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

/**
 * Applies light / dark / system preference on <html> (class + data-theme)
 * for the whole locale tree. Layout also injects a blocking script to avoid FOUC.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<UiTheme>("system");
  const [resolved, setResolved] = useState<"light" | "dark">("light");

  const hydrateTheme = useCallback((next: UiTheme) => {
    applyThemeClass(next);
    setThemeState(next);
    setResolved(resolveNow(next));
  }, []);

  useEffect(() => {
    const cached = getCachedUiPreferences().theme;
    hydrateTheme(cached);

    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const onChange = () => {
      const current = getCachedUiPreferences().theme;
      if (current === "system") hydrateTheme("system");
    };
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, [hydrateTheme]);

  const setTheme = useCallback(
    async (next: UiTheme) => {
      hydrateTheme(next);
      try {
        await updateUiPreferences({ theme: next });
      } catch {
        // Keep local selection if sync fails (offline / unauthenticated).
      }
    },
    [hydrateTheme],
  );

  const value = useMemo(
    () => ({ theme, resolved, hydrateTheme, setTheme }),
    [theme, resolved, hydrateTheme, setTheme],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error("useTheme must be used within ThemeProvider");
  }
  return ctx;
}
