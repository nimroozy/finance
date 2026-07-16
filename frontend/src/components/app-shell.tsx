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
  href: string;
  labelKey: string;
  permissions: string[];
};

type NavGroup = { labelKey: string; items: NavItem[] };

const NAV_GROUPS: NavGroup[] = [
  { labelKey: "overview", items: [
    { href: "/dashboard", labelKey: "dashboard", permissions: ["dashboard.view"] },
    { href: "/zoho/sync-health", labelKey: "syncHealth", permissions: ["zoho.view"] },
    { href: "/alerts", labelKey: "alerts", permissions: ["zoho.view", "dashboard.view"] },
  ] },
  { labelKey: "customersDebt", items: [
    { href: "/customers", labelKey: "customers", permissions: ["customers.view"] },
    { href: "/debtors", labelKey: "debtors", permissions: ["debtors.view"] },
    { href: "/invoices", labelKey: "invoices", permissions: ["invoices.view"] },
    { href: "/zoho/unmapped", labelKey: "unmapped", permissions: ["zoho.view"] },
    { href: "/zoho/mapping-conflicts", labelKey: "mappingConflicts", permissions: ["zoho.configure"] },
  ] },
  { labelKey: "fieldCollection", items: [
    { href: "/assignments", labelKey: "assignments", permissions: ["assignments.view"] },
    { href: "/customer-ownership", labelKey: "customerOwnership", permissions: ["customer_ownership.view"] },
    { href: "/temporary-assignments", labelKey: "temporaryAssignments", permissions: ["temporary_assignments.view"] },
    { href: "/ownership-conflicts", labelKey: "ownershipConflicts", permissions: ["ownership_conflicts.view"] },
    { href: "/routes", labelKey: "routes", permissions: ["routes.view"] },
    { href: "/visits", labelKey: "visits", permissions: ["visits.view"] },
    { href: "/promises", labelKey: "promises", permissions: ["promises.view"] },
  ] },
  { labelKey: "paymentsGroup", items: [
    { href: "/payments", labelKey: "payments", permissions: ["payments.view"] },
    { href: "/receipts", labelKey: "receipts", permissions: ["receipts.view"] },
    { href: "/payments/reversals", labelKey: "paymentReversals", permissions: ["reversals.request", "reversals.approve", "payments.manage"] },
    { href: "/payments/sync-failures", labelKey: "paymentSyncFailures", permissions: ["payments.view", "payments.retry_sync"] },
  ] },
  { labelKey: "cashManagement", items: [
    { href: "/wallets", labelKey: "wallets", permissions: ["wallets.view"] },
    { href: "/handovers", labelKey: "cashHandovers", permissions: ["handovers.view", "handovers.review"] },
    { href: "/cashboxes", labelKey: "cashboxes", permissions: ["cashboxes.view"] },
    { href: "/transfers", labelKey: "transfers", permissions: ["cashbox_transfers.view"] },
    { href: "/bank-deposits", labelKey: "bankDeposits", permissions: ["bank_deposits.view"] },
    { href: "/reconciliation", labelKey: "reconciliation", permissions: ["cash_reconciliation.view"] },
  ] },
  { labelKey: "administration", items: [
    { href: "/branches", labelKey: "branches", permissions: ["branches.view", "branches.manage"] },
    { href: "/zoho", labelKey: "zohoStructure", permissions: ["zoho.view"] },
    { href: "/zoho/location-mapping", labelKey: "locationMapping", permissions: ["zoho.configure"] },
    { href: "/zoho/branch-mappings", labelKey: "branchMappings", permissions: ["zoho.view"] },
    { href: "/users", labelKey: "users", permissions: ["users.view", "users.manage"] },
    { href: "/roles", labelKey: "roles", permissions: ["roles.view"] },
    { href: "/settings", labelKey: "settings", permissions: ["settings.manage"] },
    { href: "/settings/branch-payment-mappings", labelKey: "branchPaymentMappings", permissions: ["branch_payment_mapping.view"] },
    { href: "/reports/branch-receivables", labelKey: "branchReceivables", permissions: ["receivables_dashboard.view"] },
    { href: "/audit-logs", labelKey: "auditLogs", permissions: ["audit.view"] },
  ] },
];

const COLLECTOR_NAV: NavGroup[] = [
  { labelKey: "myWork", items: [
    { href: "/collector", labelKey: "collectorHome", permissions: ["assignments.view", "visits.view"] },
    { href: "/collector/permanent-customers", labelKey: "permanentCustomers", permissions: ["customer_ownership.view", "assignments.view"] },
    { href: "/collector/debtors", labelKey: "myDebtors", permissions: ["customer_ownership.view", "assignments.view"] },
    { href: "/collector/assignments", labelKey: "myAssignments", permissions: ["assignments.view"] },
    { href: "/collector/routes", labelKey: "myRoutes", permissions: ["routes.view"] },
    { href: "/collector/visits", labelKey: "myVisits", permissions: ["visits.view"] },
    { href: "/collector/payments", labelKey: "myPayments", permissions: ["payments.view"] },
    { href: "/collector/payments/new", labelKey: "newPayment", permissions: ["payments.create"] },
    { href: "/collector/wallet", labelKey: "myWallet", permissions: ["wallets.view"] },
    { href: "/collector/handovers/new", labelKey: "cashHandover", permissions: ["handovers.create", "handovers.submit"] },
    { href: "/collector/notifications", labelKey: "notifications", permissions: ["notifications.view"] },
  ] },
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
  const isCollectorRole = Boolean(user?.roles?.includes("Collector"));

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
      .map((group) => ({ ...group, items: group.items.filter((item) => hasAnyPermission(item.permissions)) }))
      .filter((group) => group.items.length > 0);
  }, [forcePassword, hasAnyPermission, isCollectorRole]);

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

  const navLink = (item: NavItem, onNavigate?: () => void) => {
    const active =
      pathname === item.href || pathname.startsWith(`${item.href}/`);
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
        {t(item.labelKey as "dashboard")}
      </Link>
    );
  };

  const navGroups = (onNavigate?: () => void) => visibleGroups.map((group) => {
    const active = group.items.some((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
    return (
      <details key={group.labelKey} open={active || undefined} className="group">
        <summary className="cursor-pointer list-none rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-muted hover:bg-sand-soft">
          <span className="flex items-center justify-between">{t(group.labelKey as "dashboard")}<span className="transition group-open:rotate-180">⌄</span></span>
        </summary>
        <div className="mt-1 space-y-1 ps-2">{group.items.map((item) => navLink(item, onNavigate))}</div>
      </details>
    );
  });

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
      <aside className="hidden border-e border-border bg-surface-elevated lg:flex lg:flex-col">
        <div className="border-b border-border px-5 py-5">
          <p className="text-lg font-semibold tracking-tight text-primary">
            {tApp("name")}
          </p>
          <p className="mt-1 text-xs text-muted">{tApp("tagline")}</p>
        </div>
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
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
            className="max-h-[70vh] space-y-1 overflow-y-auto border-b border-border bg-surface-elevated p-3 lg:hidden"
          >
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
    </div>
  );
}
