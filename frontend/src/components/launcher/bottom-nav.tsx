"use client";

import { useMemo } from "react";
import { LayoutGrid } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";
import {
  APP_CATALOG,
  BOTTOM_NAV_BY_ROLE,
  getAppById,
  isAppVisible,
  resolveLauncherRoles,
  type LauncherRole,
} from "@/config/app-catalog";
import { useAuthStore } from "@/store/auth-store";
import { getCachedUiPreferences } from "@/lib/ui-preferences";
import { cn } from "@/lib/utils";

const MAX_ITEMS = 5;

export function BottomNav() {
  const t = useTranslations("apps");
  const tShell = useTranslations("shell");
  const pathname = usePathname();
  const { user, hasAnyPermission } = useAuthStore();

  const items = useMemo(() => {
    const roles = resolveLauncherRoles(user?.roles);
    const primary: LauncherRole = roles[0] ?? "admin";
    const overrides = getCachedUiPreferences().bottom_nav_overrides;
    const ids =
      Array.isArray(overrides) && overrides.length > 0
        ? overrides
        : BOTTOM_NAV_BY_ROLE[primary] ?? BOTTOM_NAV_BY_ROLE.admin;

    const resolved: Array<{
      id: string;
      href: string;
      label: string;
      icon: React.ComponentType<{ className?: string }>;
    }> = [];

    for (const id of ids) {
      if (resolved.length >= MAX_ITEMS) break;
      if (id === "apps") {
        resolved.push({
          id: "apps",
          href: "/apps",
          label: tShell("launcher"),
          icon: LayoutGrid,
        });
        continue;
      }
      const app = getAppById(id) ?? APP_CATALOG.find((a) => a.id === id);
      if (!app || !isAppVisible(app, hasAnyPermission)) continue;
      resolved.push({
        id: app.id,
        href: app.href,
        label: t(app.nameKey),
        icon: app.icon,
      });
    }

    if (!resolved.some((i) => i.id === "apps") && resolved.length < MAX_ITEMS) {
      resolved.push({
        id: "apps",
        href: "/apps",
        label: tShell("launcher"),
        icon: LayoutGrid,
      });
    }

    return resolved.slice(0, MAX_ITEMS);
  }, [user?.roles, hasAnyPermission, t, tShell]);

  if (items.length === 0) return null;

  return (
    <nav
      className={cn(
        "fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface-elevated/95",
        "pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden",
      )}
      aria-label={tShell("bottomNav")}
      data-testid="bottom-nav"
    >
      <ul className="mx-auto flex max-w-lg items-stretch justify-around px-1">
        {items.map((item) => {
          const Icon = item.icon;
          const active =
            item.href === "/apps"
              ? pathname === "/apps"
              : pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <li key={item.id} className="flex-1">
              <Link
                href={item.href}
                className={cn(
                  "flex flex-col items-center gap-0.5 px-1 py-2 text-[10px] font-medium",
                  active ? "text-primary" : "text-muted",
                )}
                data-testid={`bottom-nav-${item.id}`}
              >
                <Icon className="h-5 w-5" aria-hidden />
                <span className="truncate">{item.label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

/** Alias for the shared component library naming (Stage 10.5A). */
export const MobileBottomNav = BottomNav;
