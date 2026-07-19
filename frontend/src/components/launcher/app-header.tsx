"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import {
  Bell,
  LayoutGrid,
  LogOut,
  Menu,
  Plus,
  Search,
  User as UserIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { useAuthStore } from "@/store/auth-store";
import { logout } from "@/lib/auth";
import { listNotifications } from "@/lib/notifications";
import { resolveAppFromPath } from "@/config/app-catalog";
import { isModuleEnabled } from "@/config/feature-flags";
import { LocaleSwitcher } from "@/components/locale-switcher";
import { ThemeSwitcher } from "@/components/theme-switcher";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { cn } from "@/lib/utils";

export function AppHeader({
  onOpenCommand,
  onToggleMobileNav,
  mobileNavOpen,
}: {
  onOpenCommand?: () => void;
  onToggleMobileNav?: () => void;
  mobileNavOpen?: boolean;
}) {
  const t = useTranslations("shell");
  const tApps = useTranslations("apps");
  const tNav = useTranslations("nav");
  const tAuth = useTranslations("auth");
  const pathname = usePathname();
  const router = useRouter();
  const { user, hasAnyPermission, clearAuth } = useAuthStore();
  const [notifCount, setNotifCount] = useState(0);
  const [quickOpen, setQuickOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const quickRef = useRef<HTMLDivElement>(null);
  const userRef = useRef<HTMLDivElement>(null);

  const currentApp = useMemo(() => resolveAppFromPath(pathname), [pathname]);
  const isLauncher = pathname === "/apps" || pathname.startsWith("/apps/");

  const canSearch = hasAnyPermission([
    "tickets.view",
    "tasks.view",
    "installations.view",
    "dashboard.view",
    "customers.view",
  ]);

  const quickCreateItems = useMemo(() => {
    const items: Array<{ href: string; label: string }> = [];
    if (isModuleEnabled("ticketing") && hasAnyPermission(["tickets.create"])) {
      items.push({ href: "/tickets/new", label: t("createTicket") });
    }
    if (isModuleEnabled("tasks") && hasAnyPermission(["tasks.create"])) {
      items.push({ href: "/tasks?create=1", label: t("createTask") });
    }
    if (isModuleEnabled("installations") && hasAnyPermission(["installations.create"])) {
      items.push({ href: "/installations?create=1", label: t("createInstallation") });
    }
    if (isModuleEnabled("crm") && hasAnyPermission(["crm.leads.create"])) {
      items.push({ href: "/crm/leads/new", label: t("createLead") });
    }
    if (hasAnyPermission(["payments.create"])) {
      items.push({ href: "/collector/payments/new", label: t("createPayment") });
    }
    return items;
  }, [hasAnyPermission, t]);

  useEffect(() => {
    if (!hasAnyPermission(["notifications.view"])) return;
    let cancelled = false;
    void listNotifications({ unread_only: true, per_page: 1 })
      .then((res) => {
        if (!cancelled) setNotifCount(res.meta?.total ?? res.data.length);
      })
      .catch(() => {
        if (!cancelled) setNotifCount(0);
      });
    return () => {
      cancelled = true;
    };
  }, [hasAnyPermission, pathname]);

  useEffect(() => {
    function onDocClick(e: MouseEvent) {
      const target = e.target as Node;
      if (quickRef.current && !quickRef.current.contains(target)) setQuickOpen(false);
      if (userRef.current && !userRef.current.contains(target)) setUserOpen(false);
    }
    document.addEventListener("mousedown", onDocClick);
    return () => document.removeEventListener("mousedown", onDocClick);
  }, []);

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

  return (
    <>
      <header
        className={cn(
          "sticky top-0 z-40 flex h-14 max-w-[100vw] items-center gap-1 overflow-x-hidden border-b border-border",
          "bg-surface-elevated/95 px-2 backdrop-blur sm:gap-2 sm:px-4",
        )}
        data-testid="app-header"
      >
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-9 w-9 shrink-0 p-0 lg:hidden"
          aria-label={tNav("menu")}
          aria-expanded={mobileNavOpen}
          onClick={onToggleMobileNav}
        >
          <Menu className="h-5 w-5" />
        </Button>

        <Link
          href="/apps"
          className={cn(
            "inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-2 text-sm font-semibold text-primary",
            "hover:bg-sand-soft",
          )}
          aria-label={t("openLauncher")}
          data-testid="launcher-button"
        >
          <LayoutGrid className="h-5 w-5" aria-hidden />
          <span className="hidden sm:inline">{t("launcher")}</span>
        </Link>

        <div className="min-w-0 flex-1 overflow-hidden">
          <p className="truncate text-sm font-medium text-foreground" data-testid="current-app-name">
            {isLauncher
              ? t("allApps")
              : currentApp
                ? tApps(currentApp.nameKey)
                : t("workspace")}
          </p>
        </div>

        <div className="flex shrink-0 items-center gap-0.5 sm:gap-2">
          {canSearch && onOpenCommand ? (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              className="hidden h-9 gap-2 md:inline-flex"
              onClick={onOpenCommand}
              aria-keyshortcuts="Control+K Meta+K"
              data-testid="header-search"
            >
              <Search className="h-4 w-4" aria-hidden />
              <span className="max-w-[6rem] truncate">{t("search")}</span>
              <kbd className="hidden rounded border border-border px-1 text-[10px] text-muted lg:inline">
                Ctrl+K
              </kbd>
            </Button>
          ) : null}

          {canSearch && onOpenCommand ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 w-9 p-0 md:hidden"
              aria-label={t("search")}
              onClick={onOpenCommand}
            >
              <Search className="h-4 w-4" />
            </Button>
          ) : null}

          {quickCreateItems.length > 0 ? (
            <div className="relative shrink-0" ref={quickRef}>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                className="h-9 gap-1"
                aria-label={t("quickCreate")}
                aria-expanded={quickOpen}
                aria-haspopup="menu"
                onClick={() => setQuickOpen((v) => !v)}
                data-testid="quick-create"
              >
                <Plus className="h-4 w-4" aria-hidden />
                <span className="hidden sm:inline">{t("quickCreate")}</span>
              </Button>
              {quickOpen ? (
                <div
                  role="menu"
                  className="absolute end-0 z-50 mt-1 min-w-[12rem] rounded-md border border-border bg-surface-elevated py-1 shadow-[var(--shadow)]"
                  data-testid="quick-create-menu"
                >
                  {quickCreateItems.map((item) => (
                    <Link
                      key={item.href}
                      href={item.href}
                      role="menuitem"
                      className="block px-3 py-2 text-sm hover:bg-sand-soft"
                      onClick={() => setQuickOpen(false)}
                    >
                      {item.label}
                    </Link>
                  ))}
                </div>
              ) : null}
            </div>
          ) : null}

          {hasAnyPermission(["notifications.view"]) ? (
            <Link
              href="/notifications"
              className="relative inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-sand-soft"
              aria-label={t("notifications")}
              data-testid="notifications-bell"
            >
              <Bell className="h-4 w-4" aria-hidden />
              {notifCount > 0 ? (
                <span className="absolute end-1 top-1 h-2 w-2 rounded-full bg-danger" aria-hidden />
              ) : null}
            </Link>
          ) : null}

          <LocaleSwitcher className="hidden h-9 min-w-20 sm:block" />
          <ThemeSwitcher className="hidden lg:inline-flex" />

          <div className="relative shrink-0" ref={userRef}>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 w-9 shrink-0 gap-2 p-0 sm:w-auto sm:px-3"
              aria-label={t("accountMenu")}
              aria-expanded={userOpen}
              aria-haspopup="menu"
              onClick={() => setUserOpen((v) => !v)}
              data-testid="user-menu"
            >
              <UserIcon className="h-4 w-4" aria-hidden />
              <span className="hidden max-w-[8rem] truncate sm:inline">{user?.name}</span>
            </Button>
            {userOpen ? (
              <div
                role="menu"
                className="absolute end-0 z-50 mt-1 min-w-[12rem] rounded-md border border-border bg-surface-elevated py-1 shadow-[var(--shadow)]"
                data-testid="user-menu-dropdown"
              >
                <div className="border-b border-border px-3 py-2">
                  <p className="text-sm font-medium">{user?.name}</p>
                  <p className="truncate text-xs text-muted">{user?.email}</p>
                </div>
                <div className="border-b border-border px-2 py-2 sm:hidden">
                  <LocaleSwitcher className="w-full" />
                </div>
                <div className="border-b border-border px-2 py-2 lg:hidden">
                  <ThemeSwitcher className="w-full justify-center" />
                </div>
                <Link
                  href="/change-password"
                  role="menuitem"
                  className="block px-3 py-2 text-sm hover:bg-sand-soft"
                  onClick={() => setUserOpen(false)}
                >
                  {tNav("changePassword")}
                </Link>
                <button
                  type="button"
                  role="menuitem"
                  className="flex w-full items-center gap-2 px-3 py-2 text-sm text-danger hover:bg-danger-soft"
                  onClick={() => {
                    setUserOpen(false);
                    setLogoutOpen(true);
                  }}
                >
                  <LogOut className="h-4 w-4" aria-hidden />
                  {tNav("logout")}
                </button>
              </div>
            ) : null}
          </div>
        </div>
      </header>

      <ConfirmDialog
        open={logoutOpen}
        title={tNav("logout")}
        description={tAuth("logoutConfirm")}
        confirmLabel={tNav("logout")}
        danger
        loading={loggingOut}
        onCancel={() => setLogoutOpen(false)}
        onConfirm={handleLogout}
      />
    </>
  );
}

/** Alias for the shared component library naming (Stage 10.5A). */
export const GlobalHeader = AppHeader;
