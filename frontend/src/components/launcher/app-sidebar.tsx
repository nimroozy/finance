"use client";

import { ChevronLeft, ChevronRight, PanelLeftClose } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { usePathname, Link } from "@/i18n/navigation";
import { Button } from "@/components/ui/button";
import type { AppNavItem } from "@/config/app-catalog";
import { cn } from "@/lib/utils";

/**
 * Desktop app sidebar: current app's context nav plus a link back to the
 * launcher when there's no context nav, and a change-password link at the
 * bottom. Collapses to icon-only width; RTL-aware collapse chevron.
 */
export function AppSidebar({
  contextNav,
  currentAppLabel,
  collapsed,
  onToggleCollapsed,
  isChangePassword,
  onNavigate,
}: {
  contextNav: AppNavItem[];
  currentAppLabel: string;
  collapsed: boolean;
  onToggleCollapsed: () => void;
  isChangePassword: boolean;
  onNavigate?: () => void;
}) {
  const t = useTranslations("nav");
  const tShell = useTranslations("shell");
  const locale = useLocale();
  const pathname = usePathname();

  const navLink = (item: AppNavItem) => {
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
        title={t(item.labelKey as "dashboard")}
        className={cn(
          "flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors",
          isActive
            ? "bg-primary text-white"
            : "text-primary/90 hover:bg-sand-soft hover:text-primary",
          collapsed && "justify-center px-2",
        )}
      >
        <span className={cn(collapsed && "sr-only")}>{t(item.labelKey as "dashboard")}</span>
        {collapsed ? (
          <span className="text-xs font-semibold" aria-hidden>
            {(t(item.labelKey as "dashboard") || "?").slice(0, 1)}
          </span>
        ) : null}
      </Link>
    );
  };

  return (
    <aside
      className="hidden border-e border-border bg-surface-elevated transition-[width] duration-200 lg:flex lg:flex-col"
      data-testid="app-sidebar"
      data-collapsed={collapsed ? "true" : "false"}
    >
      <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-3">
        {!collapsed ? (
          <p className="truncate text-xs font-semibold uppercase tracking-wider text-muted">
            {currentAppLabel}
          </p>
        ) : (
          <span className="sr-only">{tShell("navLabel")}</span>
        )}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          aria-label={collapsed ? tShell("expandSidebar") : tShell("collapseSidebar")}
          onClick={onToggleCollapsed}
          data-testid="sidebar-toggle"
        >
          {collapsed ? (
            locale === "fa" ? <ChevronLeft className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />
          ) : (
            <PanelLeftClose className="h-4 w-4" />
          )}
        </Button>
      </div>
      <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-2" aria-label={t("menu")}>
        {contextNav.length > 0 ? (
          <div className="space-y-1">{contextNav.map(navLink)}</div>
        ) : (
          <div className="space-y-1 px-2 py-2 text-sm text-muted">
            <Link
              href="/apps"
              onClick={onNavigate}
              className="block rounded-md px-3 py-2 font-medium text-primary hover:bg-sand-soft"
            >
              {t("menu")}
            </Link>
          </div>
        )}
        <Link
          href="/change-password"
          onClick={onNavigate}
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
    </aside>
  );
}
