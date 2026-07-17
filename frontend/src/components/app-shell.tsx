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
import { CommandMenu } from "@/components/ops/command-menu";
import { cn } from "@/lib/utils";
import { isModuleEnabled, roadmapPlaceholders } from "@/config/feature-flags";

type NavItem = {
  href: string;
  labelKey: string;
  permissions: string[];
  /** Optional count badge placeholder key (not fetched yet). */
  badgeKey?: string;
};

type NavGroup = {
  id: string;
  labelKey: string;
  items: NavItem[];
};

const NAV_COLLAPSE_KEY = "ops-nav-collapsed-groups";

function loadCollapsed(): Record<string, boolean> {
  if (typeof window === "undefined") return {};
  try {
    const raw = localStorage.getItem(NAV_COLLAPSE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw) as Record<string, boolean>;
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function saveCollapsed(map: Record<string, boolean>) {
  try {
    localStorage.setItem(NAV_COLLAPSE_KEY, JSON.stringify(map));
  } catch {
    // ignore quota / private mode
  }
}

const NAV_GROUPS: NavGroup[] = [
  {
    id: "home",
    labelKey: "groupHome",
    items: [
      { href: "/dashboard", labelKey: "dashboard", permissions: ["dashboard.view"] },
      { href: "/zoho/sync-health", labelKey: "syncHealth", permissions: ["zoho.view"] },
      { href: "/alerts", labelKey: "alerts", permissions: ["zoho.view", "dashboard.view"] },
    ],
  },
  {
    id: "customers",
    labelKey: "groupCustomers",
    items: [
      { href: "/customers", labelKey: "customers", permissions: ["customers.view"] },
      { href: "/debtors", labelKey: "debtors", permissions: ["debtors.view"] },
      { href: "/invoices", labelKey: "invoices", permissions: ["invoices.view"] },
      { href: "/zoho/unmapped", labelKey: "unmapped", permissions: ["zoho.view"] },
      { href: "/zoho/mapping-conflicts", labelKey: "mappingConflicts", permissions: ["zoho.configure"] },
    ],
  },
  {
    id: "serviceOps",
    labelKey: "groupServiceOps",
    items: [
      ...(isModuleEnabled("ticketing")
        ? [
            {
              href: "/tickets",
              labelKey: "tickets",
              permissions: ["tickets.view"],
              badgeKey: "tickets",
            },
          ]
        : []),
      ...(isModuleEnabled("tasks")
        ? [
            { href: "/tasks/my", labelKey: "myTasks", permissions: ["tasks.view"], badgeKey: "myTasks" },
            { href: "/tasks", labelKey: "tasksQueue", permissions: ["tasks.view"], badgeKey: "tasks" },
          ]
        : []),
      ...(isModuleEnabled("installations")
        ? [
            {
              href: "/installations",
              labelKey: "installations",
              permissions: ["installations.view"],
              badgeKey: "installations",
            },
          ]
        : []),
      ...(isModuleEnabled("ticketing")
        ? [
            {
              href: "/support/dashboard",
              labelKey: "supportDashboard",
              permissions: ["reports.support", "tickets.view"],
            },
          ]
        : []),
      ...(isModuleEnabled("tasks")
        ? [
            {
              href: "/technical/dashboard",
              labelKey: "technicalDashboard",
              permissions: ["reports.technical", "tasks.view"],
            },
          ]
        : []),
      {
        href: "/noc/dashboard",
        labelKey: "nocDashboard",
        permissions: ["escalations.view", "dashboard.view"],
      },
      ...(isModuleEnabled("whatsapp")
        ? [
            { href: "/whatsapp/inbox", labelKey: "whatsappInbox", permissions: ["whatsapp.view"] },
            { href: "/whatsapp/messages", labelKey: "whatsappMessages", permissions: ["whatsapp.view"] },
            { href: "/whatsapp/failures", labelKey: "whatsappFailures", permissions: ["whatsapp.view"] },
          ]
        : []),
    ],
  },
  {
    id: "fieldWork",
    labelKey: "groupFieldWork",
    items: [
      { href: "/assignments", labelKey: "assignments", permissions: ["assignments.view"] },
      {
        href: "/customer-ownership",
        labelKey: "customerOwnership",
        permissions: ["customer_ownership.view"],
      },
      {
        href: "/temporary-assignments",
        labelKey: "temporaryAssignments",
        permissions: ["temporary_assignments.view"],
      },
      {
        href: "/ownership-conflicts",
        labelKey: "ownershipConflicts",
        permissions: ["ownership_conflicts.view"],
      },
      { href: "/routes", labelKey: "routes", permissions: ["routes.view"] },
      { href: "/visits", labelKey: "visits", permissions: ["visits.view"] },
      { href: "/promises", labelKey: "promises", permissions: ["promises.view"] },
      { href: "/collectors", labelKey: "collectors", permissions: ["collectors.view", "collectors.manage"] },
    ],
  },
  {
    id: "finance",
    labelKey: "groupFinance",
    items: [
      { href: "/payments", labelKey: "payments", permissions: ["payments.view"] },
      { href: "/receipts", labelKey: "receipts", permissions: ["receipts.view"] },
      {
        href: "/payments/reversals",
        labelKey: "paymentReversals",
        permissions: ["reversals.request", "reversals.approve", "payments.manage"],
      },
      {
        href: "/payments/sync-failures",
        labelKey: "paymentSyncFailures",
        permissions: ["payments.view", "payments.retry_sync"],
      },
      { href: "/wallets", labelKey: "wallets", permissions: ["wallets.view"] },
      {
        href: "/handovers",
        labelKey: "cashHandovers",
        permissions: ["handovers.view", "handovers.review"],
      },
      {
        href: "/custody-reversals",
        labelKey: "custodyReversals",
        permissions: ["custody_reversals.review", "reversals.approve"],
      },
      { href: "/cashboxes", labelKey: "cashboxes", permissions: ["cashboxes.view"] },
      { href: "/transfers", labelKey: "transfers", permissions: ["cashbox_transfers.view"] },
      { href: "/bank-deposits", labelKey: "bankDeposits", permissions: ["bank_deposits.view"] },
      {
        href: "/reconciliation",
        labelKey: "reconciliation",
        permissions: ["cash_reconciliation.view"],
      },
      {
        href: "/finance/tasks",
        labelKey: "financeQueue",
        permissions: ["installations.view", "dashboard.view"],
      },
      {
        href: "/reports/branch-receivables",
        labelKey: "branchReceivables",
        permissions: ["receivables_dashboard.view"],
      },
      {
        href: "/reports/payments",
        labelKey: "paymentReports",
        permissions: ["reports.payments", "payments.view"],
      },
    ],
  },
  {
    id: "management",
    labelKey: "groupManagement",
    items: [
      {
        href: "/management/service-operations",
        labelKey: "managementOps",
        permissions: ["reports.management", "dashboard.view"],
      },
      {
        href: "/reports/collection",
        labelKey: "collectionReports",
        permissions: ["reports.assignments", "reports.visits", "reports.promises"],
      },
    ],
  },
  {
    id: "administration",
    labelKey: "groupAdministration",
    items: [
      { href: "/branches", labelKey: "branches", permissions: ["branches.view", "branches.manage"] },
      { href: "/zoho", labelKey: "zohoStructure", permissions: ["zoho.view"] },
      {
        href: "/zoho/location-mapping",
        labelKey: "locationMapping",
        permissions: ["zoho.configure"],
      },
      { href: "/zoho/branch-mappings", labelKey: "branchMappings", permissions: ["zoho.view"] },
      { href: "/users", labelKey: "users", permissions: ["users.view", "users.manage"] },
      { href: "/roles", labelKey: "roles", permissions: ["roles.view"] },
      { href: "/settings", labelKey: "settings", permissions: ["settings.manage"] },
      {
        href: "/settings/branch-payment-mappings",
        labelKey: "branchPaymentMappings",
        permissions: ["branch_payment_mapping.view"],
      },
      {
        href: "/settings/customer-prefix-mappings",
        labelKey: "customerPrefixMappings",
        permissions: ["customer_prefix_mapping.view"],
      },
      {
        href: "/settings/mapping-cleanup",
        labelKey: "mappingCleanup",
        permissions: ["customer_prefix_mapping.view"],
      },
      ...(isModuleEnabled("whatsapp")
        ? [
            {
              href: "/settings/whatsapp",
              labelKey: "whatsappSettings",
              permissions: ["whatsapp.manage"],
            },
            {
              href: "/settings/whatsapp-rules",
              labelKey: "whatsappRules",
              permissions: ["whatsapp.manage"],
            },
            {
              href: "/whatsapp/templates",
              labelKey: "whatsappTemplates",
              permissions: ["whatsapp.view"],
            },
          ]
        : []),
      ...(isModuleEnabled("ticketing")
        ? [
            {
              href: "/settings/ticket-types",
              labelKey: "ticketTypes",
              permissions: ["ticket_types.manage"],
            },
            {
              href: "/settings/sla-policies",
              labelKey: "slaPolicies",
              permissions: ["sla.manage"],
            },
            {
              href: "/settings/escalation-rules",
              labelKey: "escalationRules",
              permissions: ["escalations.manage", "sla.manage"],
            },
          ]
        : []),
      ...(isModuleEnabled("tasks")
        ? [
            {
              href: "/settings/task-templates",
              labelKey: "taskTemplates",
              permissions: ["task_templates.manage"],
            },
          ]
        : []),
      { href: "/audit-logs", labelKey: "auditLogs", permissions: ["audit.view"] },
    ],
  },
];

const COLLECTOR_NAV: NavGroup[] = [
  {
    id: "home",
    labelKey: "groupHome",
    items: [
      {
        href: "/collector",
        labelKey: "collectorHome",
        permissions: ["assignments.view", "visits.view"],
      },
      {
        href: "/collector/notifications",
        labelKey: "notifications",
        permissions: ["notifications.view"],
      },
    ],
  },
  {
    id: "fieldWork",
    labelKey: "groupFieldWork",
    items: [
      {
        href: "/collector/permanent-customers",
        labelKey: "permanentCustomers",
        permissions: ["customer_ownership.view", "assignments.view"],
      },
      {
        href: "/collector/debtors",
        labelKey: "myDebtors",
        permissions: ["customer_ownership.view", "assignments.view"],
      },
      {
        href: "/collector/assignments",
        labelKey: "myAssignments",
        permissions: ["assignments.view"],
      },
      { href: "/collector/routes", labelKey: "myRoutes", permissions: ["routes.view"] },
      { href: "/collector/visits", labelKey: "myVisits", permissions: ["visits.view"] },
      { href: "/collector/payments", labelKey: "myPayments", permissions: ["payments.view"] },
      {
        href: "/collector/payments/new",
        labelKey: "newPayment",
        permissions: ["payments.create"],
      },
      { href: "/collector/wallet", labelKey: "myWallet", permissions: ["wallets.view"] },
      {
        href: "/collector/handovers/new",
        labelKey: "cashHandover",
        permissions: ["handovers.create", "handovers.submit"],
      },
      ...(isModuleEnabled("tasks")
        ? [{ href: "/tasks/my", labelKey: "myTasks", permissions: ["tasks.view"] }]
        : []),
      ...(isModuleEnabled("ticketing")
        ? [{ href: "/tickets", labelKey: "tickets", permissions: ["tickets.view"] }]
        : []),
    ],
  },
];

export function AppShell({ children }: { children: React.ReactNode }) {
  const t = useTranslations("nav");
  const tApp = useTranslations("app");
  const tAuth = useTranslations("auth");
  const tCommon = useTranslations("common");
  const tOps = useTranslations("opsUi");
  const pathname = usePathname();
  const router = useRouter();
  const { token, user, hydrated, hasAnyPermission, clearAuth } = useAuthStore();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [logoutOpen, setLogoutOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [commandOpen, setCommandOpen] = useState(false);
  const [quickCreateOpen, setQuickCreateOpen] = useState(false);
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  const forcePassword = Boolean(user?.force_password_change);
  const isChangePassword = pathname.includes("/change-password");
  const isCollectorRole = Boolean(user?.roles?.includes("Collector"));

  useEffect(() => {
    setCollapsed(loadCollapsed());
  }, []);

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

  const visibleGroups = useMemo(() => {
    if (forcePassword) return [];
    return (isCollectorRole ? COLLECTOR_NAV : NAV_GROUPS)
      .map((group) => ({
        ...group,
        items: group.items.filter((item) => hasAnyPermission(item.permissions)),
      }))
      .filter((group) => group.items.length > 0);
  }, [forcePassword, hasAnyPermission, isCollectorRole]);

  const roadmapItems = useMemo(() => roadmapPlaceholders(), []);
  const showRoadmap =
    !forcePassword &&
    !isCollectorRole &&
    hasAnyPermission(["dashboard.view", "settings.manage", "branches.manage"]);

  const canSearch =
    !forcePassword &&
    hasAnyPermission(["tickets.view", "tasks.view", "installations.view", "dashboard.view"]);

  const quickCreateItems = useMemo(() => {
    const items: Array<{ href: string; labelKey: string }> = [];
    if (isModuleEnabled("ticketing") && hasAnyPermission(["tickets.create"])) {
      items.push({ href: "/tickets/new", labelKey: "createTicket" });
    }
    if (isModuleEnabled("tasks") && hasAnyPermission(["tasks.create"])) {
      items.push({ href: "/tasks?create=1", labelKey: "createTask" });
    }
    if (isModuleEnabled("installations") && hasAnyPermission(["installations.create"])) {
      items.push({ href: "/installations?create=1", labelKey: "createInstallation" });
    }
    return items;
  }, [hasAnyPermission]);

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

  function setGroupCollapsed(id: string, isCollapsed: boolean) {
    const next = { ...collapsed, [id]: isCollapsed };
    setCollapsed(next);
    saveCollapsed(next);
  }

  function isGroupOpen(group: NavGroup, pathActive: boolean) {
    if (pathActive) return true;
    return collapsed[group.id] !== true;
  }

  const navLink = (item: NavItem, onNavigate?: () => void) => {
    // Keep /tasks vs /tasks/my distinct in active styling
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
          "flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors",
          isActive
            ? "bg-primary text-white"
            : "text-primary/90 hover:bg-sand-soft hover:text-primary",
        )}
      >
        <span>{t(item.labelKey as "dashboard")}</span>
        {item.badgeKey ? (
          <span
            className={cn(
              "rounded px-1.5 text-[10px] font-semibold",
              isActive ? "bg-white/20" : "bg-sand-soft text-muted",
            )}
            aria-hidden
          >
            ·
          </span>
        ) : null}
      </Link>
    );
  };

  const roadmapNav = (
    <details key="roadmap" className="group">
      <summary className="cursor-pointer list-none rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-muted hover:bg-sand-soft">
        <span className="flex items-center justify-between">
          {t("roadmap")}
          <span className="transition group-open:rotate-180">⌄</span>
        </span>
      </summary>
      <div className="mt-1 space-y-1 ps-2">
        {roadmapItems.map((mod) => (
          <div
            key={mod.id}
            className="rounded-md px-3 py-2 text-sm text-muted/80"
            title={t("roadmapSoonHint")}
          >
            <span className="block font-medium text-muted">{t(`roadmap_${mod.id}` as "dashboard")}</span>
            <span className="text-xs">{t("roadmapStage", { stage: mod.stage })}</span>
          </div>
        ))}
      </div>
    </details>
  );

  const navGroups = (onNavigate?: () => void) => (
    <>
      {visibleGroups.map((group) => {
        const pathActive = group.items.some((item) => {
          if (item.href === "/tasks") {
            return (
              pathname === "/tasks" ||
              (pathname.startsWith("/tasks/") && !pathname.startsWith("/tasks/my"))
            );
          }
          if (item.href === "/tasks/my") {
            return pathname === "/tasks/my" || pathname.startsWith("/tasks/my/");
          }
          return pathname === item.href || pathname.startsWith(`${item.href}/`);
        });
        const open = isGroupOpen(group, pathActive);
        return (
          <details
            key={group.id}
            open={open}
            className="group"
            onToggle={(e) => {
              setGroupCollapsed(group.id, !e.currentTarget.open);
            }}
          >
            <summary className="cursor-pointer list-none rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-muted hover:bg-sand-soft">
              <span className="flex items-center justify-between">
                {t(group.labelKey as "dashboard")}
                <span className="transition group-open:rotate-180">⌄</span>
              </span>
            </summary>
            <div className="mt-1 space-y-1 ps-2">
              {group.items.map((item) => navLink(item, onNavigate))}
            </div>
          </details>
        );
      })}
      {showRoadmap ? roadmapNav : null}
    </>
  );

  const shellTools = (
    <div className="flex flex-wrap items-center gap-2">
      {canSearch ? (
        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={() => setCommandOpen(true)}
          aria-keyshortcuts="Control+K Meta+K"
        >
          {tOps("search")}
          <kbd className="ms-1 hidden rounded border border-border px-1 text-[10px] text-muted sm:inline">
            Ctrl+K
          </kbd>
        </Button>
      ) : null}
      {quickCreateItems.length > 0 ? (
        <div className="relative">
          <Button
            type="button"
            size="sm"
            variant="secondary"
            aria-expanded={quickCreateOpen}
            aria-haspopup="menu"
            onClick={() => setQuickCreateOpen((v) => !v)}
          >
            {tOps("quickCreate")}
          </Button>
          {quickCreateOpen ? (
            <div
              role="menu"
              className="absolute end-0 z-40 mt-1 min-w-[11rem] rounded-md border border-border bg-surface-elevated py-1 shadow-[var(--shadow)]"
            >
              {quickCreateItems.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  role="menuitem"
                  className="block px-3 py-2 text-sm hover:bg-sand-soft"
                  onClick={() => setQuickCreateOpen(false)}
                >
                  {tOps(item.labelKey as "createTicket")}
                </Link>
              ))}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
      <aside className="hidden border-e border-border bg-surface-elevated lg:flex lg:flex-col">
        <div className="border-b border-border px-5 py-5">
          <p className="text-lg font-semibold tracking-tight text-primary">{tApp("name")}</p>
          <p className="mt-1 text-xs text-muted">{tApp("tagline")}</p>
          <div className="mt-3">{shellTools}</div>
        </div>
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label={t("menu")}>
          {navGroups()}
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
          <Button variant="secondary" className="w-full" onClick={() => setLogoutOpen(true)}>
            {t("logout")}
          </Button>
        </div>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-border bg-surface-elevated/95 px-4 py-3 backdrop-blur lg:hidden">
          <div className="min-w-0">
            <p className="font-semibold text-primary">{tApp("name")}</p>
            <p className="truncate text-xs text-muted">{user.name}</p>
          </div>
          <div className="flex items-center gap-2">
            {canSearch ? (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setCommandOpen(true)}
                aria-label={tOps("search")}
              >
                ⌕
              </Button>
            ) : null}
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
            className="max-h-[75vh] space-y-1 overflow-y-auto border-b border-border bg-surface-elevated p-3 lg:hidden"
            aria-label={t("menu")}
          >
            <div className="mb-3 flex flex-wrap gap-2 border-b border-border pb-3">
              {shellTools}
            </div>
            {navGroups(() => setMobileOpen(false))}
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

        <main
          className={cn(
            "mx-auto w-full flex-1 px-4 py-6 sm:px-6",
            isCollectorRole ? "max-w-lg" : "max-w-6xl",
          )}
        >
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

      {canSearch ? <CommandMenu open={commandOpen} onOpenChange={setCommandOpen} /> : null}
    </div>
  );
}
