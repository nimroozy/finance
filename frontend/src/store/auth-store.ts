import { create } from "zustand";
import { persist } from "zustand/middleware";
import type { AuthUser } from "@/lib/types";

interface AuthState {
  token: string | null;
  user: AuthUser | null;
  hydrated: boolean;
  setAuth: (token: string, user: AuthUser) => void;
  setUser: (user: AuthUser) => void;
  clearAuth: () => void;
  setHydrated: (value: boolean) => void;
  hasPermission: (permission: string) => boolean;
  hasAnyPermission: (permissions: string[]) => boolean;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      hydrated: false,
      setAuth: (token, user) => set({ token, user }),
      setUser: (user) => set({ user }),
      clearAuth: () => set({ token: null, user: null }),
      setHydrated: (value) => set({ hydrated: value }),
      hasPermission: (permission) => {
        const user = get().user;
        if (!user) return false;
        return user.permissions.includes(permission);
      },
      hasAnyPermission: (permissions) => {
        const user = get().user;
        if (!user) return false;
        return permissions.some((p) => user.permissions.includes(p));
      },
    }),
    {
      name: "auth-storage",
      partialize: (state) => ({
        token: state.token,
        user: state.user,
      }),
      onRehydrateStorage: () => (state) => {
        state?.setHydrated(true);
      },
    },
  ),
);
