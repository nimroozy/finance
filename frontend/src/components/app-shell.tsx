"use client";

import { useEffect, useMemo, useState } from "react";
import { PanelLeft } from "lucide-react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter, Link } from "@/i18n/navigation";
import { useAuthStore } from "@/store/auth-store";
import { LoadingState } from "@/components/ui/layout";
import { Button } from "@/components/ui/button";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { CommandMenu } from "@/components/ops/command-menu";
import { GlobalHeader } from "@/components/launcher/app-header";
import { MobileBottomNav } from "@/components/launcher/bottom-nav";
import { AppSidebar } from "@/components/launcher/app-sidebar";
import {
  APP_CONTEXT_NAV,
  resolveAppFromPath,
  type AppNavItem,
} from "@/config/app-catalog";
import { fetchUiPreferences, updateUiPreferences } from "@/lib/ui-preferences";
import { useTheme } from "@/components/theme-provider";
import { cn } from "@/lib/utils";

const SIDEBAR_KEY = "ops-sidebar-collapsed";

function loadSidebarCollapsed(): boolean {
  if (typeof window === "undefined") return false;
  try {
    return localStorage.getItem(SIDEBAR_KEY) === "1";
  } catch {
    return false;
  }
}

function saveSidebarCollapsed(value: boolean) {
  try {
    localStorage.setItem(SIDEBAR_KEY, value ? "1" : "0");
  } catch {
    // ignore
  }
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const t = useTranslations("nav");
  const tShell = useTranslations("shell");
  const tApps = useTranslations("apps");
  const tCommon = useTranslations("common");
  const pathname = usePathname();
  const router = useRouter();
  const { token, user, hydrated, hasAnyPermission } = useAuthStore();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [commandOpen, setCommandOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const { hydrateTheme } = useTheme();

  const forcePassword = Boolean(user?.force_password_change);
  const isChangePassword = pathname.includes("/change-password");
  const isLauncher = pathname === "/apps" || pathname.startsWith("/apps/");
  const currentApp = useMemo(() => resolveAppFromPath(pathname), [pathname]);
  const currentAppLabel = currentApp ? tApps(currentApp.nameKey) : tShell("allApps");

  useEffect(() => {
    setSidebarCollapsed(loadSidebarCollapsed());
    void fetchUiPreferences()
      .then((p) => hydrateTheme(p.theme))
      .catch(() => undefined);
  }, [hydrateTheme]);

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

  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  const contextNav = useMemo(() => {
    if (forcePassword || isLauncher || !currentApp) return [] as AppNavItem[];
    const items = APP_CONTEXT_NAV[currentApp.id] ?? [];
    return items.filter((item) => hasAnyPermission(item.permissions));
  }, [forcePassword, isLauncher, currentApp, hasAnyPermission]);

  const canSearch =
    !forcePassword &&
    hasAnyPermission([
      "tickets.view",
      "tasks.view",
      "installations.view",
      "dashboard.view",
      "customers.view",
      "services.view",
      "crm.leads.view",
      "payments.view",
      "inventory.products.view",
      "inventory.equipment.view",
      "users.view",
    ]);

  if (!hydrated || !token || !user) {
    return <LoadingState label={tCommon("loading")} />;
  }

  if (forcePassword && !isChangePassword) {
    return <LoadingState label={tCommon("loading")} />;
  }

  function toggleSidebar() {
    const next = !sidebarCollapsed;
    setSidebarCollapsed(next);
    saveSidebarCollapsed(next);
    void updateUiPreferences({
      collapsed_nav_groups: { __sidebar: next },
    }).catch(() => undefined);
  }

  const mobileNavLink = (item: AppNavItem, onNavigate?: () => void) => {
    const isActive =
      item.href === "/tasks"
        ? pathname === "/tasks" || (pathname.startsWith("/tasks/") && !pathname.startsWith("/tasks/my"))
        : item.href === "/tasks/my"
          ? pathname === "/tasks/my" || pathname.startsWith("/tasks/my/")
          : pathname === item.href || pathname.startsWith(`${item.href}/`);

    return (
      <Link
        key={item.href}
        href={item.href}
        onClick={onNavigate}
        className={cn(
          "flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors",
          isActive
            ? "bg-primary text-white"
            : "text-primary/90 hover:bg-sand-soft hover:text-primary",
        )}
      >
        {t(item.labelKey as "dashboard")}
      </Link>
    );
  };

  const showDesktopSidebar = !forcePassword && !isLauncher;
  const breadcrumbItems = forcePassword
    ? []
    : isLauncher
      ? []
      : [
          { label: tShell("allApps"), href: "/apps" },
          { label: currentAppLabel },
        ];

  return (
    <div
      className={cn(
        "min-h-screen max-w-[100vw] overflow-x-hidden",
        showDesktopSidebar && (sidebarCollapsed ? "lg:grid lg:grid-cols-[72px_1fr]" : "lg:grid lg:grid-cols-[220px_1fr]"),
      )}
      data-testid="app-shell"
    >
      {showDesktopSidebar ? (
        <AppSidebar
          contextNav={contextNav}
          currentAppLabel={currentAppLabel}
          collapsed={sidebarCollapsed}
          onToggleCollapsed={toggleSidebar}
          isChangePassword={isChangePassword}
        />
      ) : null}

      <div className="flex min-h-screen flex-col">
        {!forcePassword ? (
          <GlobalHeader
            onOpenCommand={canSearch ? () => setCommandOpen(true) : undefined}
            onToggleMobileNav={() => setMobileOpen((v) => !v)}
            mobileNavOpen={mobileOpen}
          />
        ) : null}

        {mobileOpen && !forcePassword ? (
          <nav
            id="mobile-nav"
            className="max-h-[70vh] space-y-1 overflow-y-auto border-b border-border bg-surface-elevated p-3 lg:hidden"
            aria-label={t("menu")}
            data-testid="mobile-drawer"
          >
            <div className="mb-2 flex items-center justify-between">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                {currentAppLabel}
              </p>
              <Button type="button" variant="ghost" size="sm" onClick={() => setMobileOpen(false)}>
                {t("close")}
              </Button>
            </div>
            {contextNav.length > 0 ? (
              <div className="space-y-1">
                {contextNav.map((item) => mobileNavLink(item, () => setMobileOpen(false)))}
              </div>
            ) : null}
            <Link
              href="/change-password"
              onClick={() => setMobileOpen(false)}
              className={cn(
                "block rounded-md px-3 py-2 text-sm font-medium transition-colors",
                isChangePassword
                  ? "bg-primary text-white"
                  : "text-muted hover:bg-sand-soft hover:text-primary",
              )}
            >
              {t("changePassword")}
            </Link>
            <Link
              href="/apps"
              onClick={() => setMobileOpen(false)}
              className="mt-2 block rounded-md px-3 py-2 text-sm font-medium text-primary hover:bg-sand-soft"
            >
              <span className="inline-flex items-center gap-2">
                <PanelLeft className="h-4 w-4" aria-hidden />
                {tShell("launcher")}
              </span>
            </Link>
          </nav>
        ) : null}

        <main
          className={cn("mx-auto w-full flex-1 px-4 py-6 sm:px-6", "max-w-6xl", "pb-24 lg:pb-6")}
        >
          {breadcrumbItems.length > 0 ? <Breadcrumbs items={breadcrumbItems} /> : null}
          {children}
        </main>

        {!forcePassword ? <MobileBottomNav /> : null}
      </div>

      {canSearch ? <CommandMenu open={commandOpen} onOpenChange={setCommandOpen} /> : null}
    </div>
  );
}
