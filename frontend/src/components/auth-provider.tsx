"use client";

import { useEffect } from "react";
import { configureApi } from "@/lib/api";
import { useAuthStore } from "@/store/auth-store";

export function AuthProvider({ children }: { children: React.ReactNode }) {
  useEffect(() => {
    configureApi({
      getToken: () => useAuthStore.getState().token,
      onUnauthorized: () => {
        useAuthStore.getState().clearAuth();
        const locale = window.location.pathname.split("/")[1] || "en";
        const safeLocale = locale === "fa" ? "fa" : "en";
        if (!window.location.pathname.includes("/login")) {
          window.location.href = `/${safeLocale}/login`;
        }
      },
    });

    if (!useAuthStore.getState().hydrated) {
      const unsub = useAuthStore.persist.onFinishHydration(() => {
        useAuthStore.getState().setHydrated(true);
      });
      if (useAuthStore.persist.hasHydrated()) {
        useAuthStore.getState().setHydrated(true);
      }
      return unsub;
    }
  }, []);

  return <>{children}</>;
}
