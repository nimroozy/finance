"use client";

import { useEffect, useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter, Link } from "@/i18n/navigation";
import { useAuthStore } from "@/store/auth-store";
import { logout } from "@/lib/auth";
import { LocaleSwitcher } from "@/components/locale-switcher";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { LoadingState } from "@/components/ui/layout";
import { cn } from "@/lib/utils";

type NavItem = {
  href: "/dashboard" | "/users" | "/roles" | "/branches" | "/audit-logs" | "/settings";
  labelKey:
    | "dashboard"
    | "users"
    | "roles"
    | "branches"
    | "auditLogs"
    | "settings";
  permissions: string[];
};

const NAV_ITEMS: NavItem[] = [
  { href: "/dashboard", labelKey: "dashboard", permissions: ["dashboard.view"] },
  {
    href: "/users",
    labelKey: "users",
    permissions: ["users.view", "users.manage"],
  },
  { href: "/roles", labelKey: "roles", permissions: ["roles.view"] },
  {
    href: "/branches",
    labelKey: "branches",
    permissions: ["branches.view", "branches.manage"],
  },
  { href: "/audit-logs", labelKey: "auditLogs", permissions: ["audit.view"] },
  {
    href: "/settings",
    labelKey: "settings",
    permissions: ["settings.manage"],
  },
];

export function AppShell({ children }: { children: React.ReactNode }) {
  const t = useTranslations("nav");
  const tApp = useTranslations("app");
  const tAuth = useTranslations("auth");
  const tCommon = useTranslations("common");
  const pathname = usePathname();
  const router = useRouter();
  const { token, user, hydrated, hasAnyPermission, clearAuth } = useAuthStore();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  const forcePassword = Boolean(user?.force_password_change);
  const isChangePassword = pathname.includes("/change-password");

  useEffect(() => {
    if (!hydrated) return;
    if (!token || !user) {
      router.replace("/login");
      return;
    }
    if (forcePassword && !isChangePassword) {
      router.replace("/change-password");
    }
  }, [hydrated, token, user, forcePassword, isChangePassword, router]);

  const visibleNav = useMemo(() => {
    if (forcePassword) return [];
    return NAV_ITEMS.filter((item) => hasAnyPermission(item.permissions));
  }, [forcePassword, hasAnyPermission]);

  if (!hydrated || !token || !user) {
    return <LoadingState label={tCommon("loading")} />;
  }

  if (forcePassword && !isChangePassword) {
    return <LoadingState label={tCommon("loading")} />;
  }

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await logout();
    } finally {
      clearAuth();
      setLoggingOut(false);
      setLogoutOpen(false);
      router.replace("/login");
    }
  }

  const navLink = (
    item: NavItem,
    onNavigate?: () => void,
  ) => {
    const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
    return (
      <Link
        key={item.href}
        href={item.href}
        onClick={onNavigate}
        className={cn(
          "block rounded-md px-3 py-2 text-sm font-medium transition-colors",
          active
            ? "bg-primary text-white"
            : "text-primary/90 hover:bg-sand-soft hover:text-primary",
        )}
      >
        {t(item.labelKey)}
      </Link>
    );
  };

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
      <aside className="hidden border-e border-border bg-surface-elevated lg:flex lg:flex-col">
        <div className="border-b border-border px-5 py-5">
          <p className="text-lg font-semibold tracking-tight text-primary">
            {tApp("name")}
          </p>
          <p className="mt-1 text-xs text-muted">{tApp("tagline")}</p>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3">
          {visibleNav.map((item) => navLink(item))}
          <Link
            href="/change-password"
            className={cn(
              "mt-auto rounded-md px-3 py-2 text-sm font-medium transition-colors",
              isChangePassword
                ? "bg-primary text-white"
                : "text-muted hover:bg-sand-soft hover:text-primary",
            )}
          >
            {t("changePassword")}
          </Link>
        </nav>
        <div className="space-y-3 border-t border-border p-4">
          <div>
            <p className="text-sm font-medium text-foreground">{user.name}</p>
            <p className="text-xs text-muted">{user.email}</p>
          </div>
          <LocaleSwitcher className="w-full" />
          <Button
            variant="secondary"
            className="w-full"
            onClick={() => setLogoutOpen(true)}
          >
            {t("logout")}
          </Button>
        </div>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-border bg-surface-elevated/95 px-4 py-3 backdrop-blur lg:hidden">
          <div>
            <p className="font-semibold text-primary">{tApp("name")}</p>
            <p className="text-xs text-muted">{user.name}</p>
          </div>
          <div className="flex items-center gap-2">
            <LocaleSwitcher />
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setMobileOpen((v) => !v)}
              aria-expanded={mobileOpen}
              aria-controls="mobile-nav"
            >
              {mobileOpen ? t("close") : t("menu")}
            </Button>
          </div>
        </header>

        {mobileOpen ? (
          <nav
            id="mobile-nav"
            className="space-y-1 border-b border-border bg-surface-elevated p-3 lg:hidden"
          >
            {visibleNav.map((item) =>
              navLink(item, () => setMobileOpen(false)),
            )}
            <Link
              href="/change-password"
              onClick={() => setMobileOpen(false)}
              className="block rounded-md px-3 py-2 text-sm font-medium text-muted hover:bg-sand-soft"
            >
              {t("changePassword")}
            </Link>
            <Button
              variant="secondary"
              className="mt-2 w-full"
              onClick={() => {
                setMobileOpen(false);
                setLogoutOpen(true);
              }}
            >
              {t("logout")}
            </Button>
          </nav>
        ) : null}

        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
          {children}
        </main>
      </div>

      <ConfirmDialog
        open={logoutOpen}
        title={t("logout")}
        description={tAuth("logoutConfirm")}
        confirmLabel={t("logout")}
        danger
        loading={loggingOut}
        onCancel={() => setLogoutOpen(false)}
        onConfirm={handleLogout}
      />
    </div>
  );
}
