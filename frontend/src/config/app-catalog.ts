import type { LucideIcon } from "lucide-react";
import {
  LayoutDashboard,
  Wallet,
  MapPinned,
  ListTodo,
  Headphones,
  Radar,
  Wrench,
  Users,
  ContactRound,
  Package,
  Cpu,
  Warehouse,
  ArrowLeftRight,
  ShoppingCart,
  RadioTower,
  Hammer,
  MessageCircle,
  Bell,
  BarChart3,
  Building2,
  UserCog,
  ScrollText,
  Settings,
  GitBranch,
  FileText,
  CalendarClock,
  Cable,
} from "lucide-react";
import { isModuleEnabled, type PlatformModule } from "@/config/feature-flags";

/** Logical launcher personas used for default ordering (not Spatie role names). */
export type LauncherRole =
  | "collector"
  | "support"
  | "noc"
  | "sales"
  | "inventory"
  | "manager"
  | "admin";

export type AppGroupId =
  | "operations"
  | "finance"
  | "field"
  | "crm"
  | "inventory"
  | "comms"
  | "insights"
  | "admin";

export type CatalogApp = {
  id: string;
  href: string;
  icon: LucideIcon;
  group: AppGroupId;
  /** User needs ANY of these permissions to see the app. Empty = always visible when authenticated. */
  permissions: string[];
  featureFlag?: PlatformModule;
  nameKey: string;
  descriptionKey: string;
  /** Path prefixes that belong to this app (for shell context + current app detection). */
  matchPrefixes: string[];
  /** Lower = earlier for that persona. Missing = 999. */
  roleDefaultOrder: Partial<Record<LauncherRole, number>>;
};

/**
 * Unified app catalog (Stage 9.1 launcher).
 * Deploy lineage may also include Stage 10 service lifecycle routes.
 */
export const APP_CATALOG: CatalogApp[] = [
  {
    id: "dashboard",
    href: "/dashboard",
    icon: LayoutDashboard,
    group: "insights",
    permissions: ["dashboard.view"],
    nameKey: "dashboard",
    descriptionKey: "dashboardDesc",
    matchPrefixes: ["/dashboard"],
    roleDefaultOrder: { admin: 1, manager: 1, support: 8, noc: 8, sales: 10, inventory: 10 },
  },
  {
    id: "payments",
    href: "/payments",
    icon: Wallet,
    group: "finance",
    permissions: ["payments.view"],
    nameKey: "payments",
    descriptionKey: "paymentsDesc",
    matchPrefixes: ["/payments", "/receipts", "/wallets", "/handovers", "/cashboxes", "/bank-deposits", "/reconciliation", "/custody-reversals", "/collector/payments", "/collector/wallet", "/collector/handovers"],
    roleDefaultOrder: { collector: 2, manager: 2, admin: 3 },
  },
  {
    id: "collections",
    href: "/assignments",
    icon: MapPinned,
    group: "field",
    permissions: ["assignments.view", "visits.view"],
    nameKey: "collections",
    descriptionKey: "collectionsDesc",
    matchPrefixes: [
      "/assignments",
      "/visits",
      "/routes",
      "/promises",
      "/collectors",
      "/customer-ownership",
      "/temporary-assignments",
      "/ownership-conflicts",
      "/debtors",
      "/collector",
    ],
    roleDefaultOrder: { collector: 1, manager: 3, admin: 4 },
  },
  {
    id: "tasks",
    href: "/tasks",
    icon: ListTodo,
    group: "operations",
    permissions: ["tasks.view"],
    featureFlag: "tasks",
    nameKey: "tasks",
    descriptionKey: "tasksDesc",
    matchPrefixes: ["/tasks", "/technical/dashboard", "/finance/tasks"],
    roleDefaultOrder: { support: 3, noc: 2, manager: 5, admin: 6 },
  },
  {
    id: "support",
    href: "/tickets",
    icon: Headphones,
    group: "operations",
    permissions: ["tickets.view"],
    featureFlag: "ticketing",
    nameKey: "support",
    descriptionKey: "supportDesc",
    matchPrefixes: ["/tickets", "/support/dashboard"],
    roleDefaultOrder: { support: 1, noc: 3, manager: 6, admin: 7 },
  },
  {
    id: "noc",
    href: "/noc/services",
    icon: Radar,
    group: "operations",
    permissions: ["escalations.view", "dashboard.view", "services.noc.view"],
    nameKey: "noc",
    descriptionKey: "nocDesc",
    matchPrefixes: ["/noc"],
    roleDefaultOrder: { noc: 1, support: 4, manager: 7, admin: 8 },
  },
  {
    id: "installations",
    href: "/installations",
    icon: Wrench,
    group: "operations",
    permissions: ["installations.view"],
    featureFlag: "installations",
    nameKey: "installations",
    descriptionKey: "installationsDesc",
    matchPrefixes: ["/installations"],
    roleDefaultOrder: { sales: 4, support: 5, noc: 4, manager: 8, admin: 9 },
  },
  {
    id: "services",
    href: "/services/dashboard",
    icon: Cable,
    group: "operations",
    permissions: ["services.view", "services.dashboard.view"],
    featureFlag: "services",
    nameKey: "services",
    descriptionKey: "servicesDesc",
    matchPrefixes: ["/services"],
    roleDefaultOrder: { noc: 2, support: 3, manager: 5, admin: 6, sales: 5 },
  },
  {
    id: "customers",
    href: "/customers",
    icon: Users,
    group: "finance",
    permissions: ["customers.view"],
    nameKey: "customers",
    descriptionKey: "customersDesc",
    matchPrefixes: ["/customers", "/invoices"],
    roleDefaultOrder: { support: 2, sales: 3, collector: 4, manager: 4, admin: 5 },
  },
  {
    id: "crm",
    href: "/crm/dashboard",
    icon: ContactRound,
    group: "crm",
    permissions: ["crm.leads.view", "crm.reports.view", "crm.pipeline.manage"],
    featureFlag: "crm",
    nameKey: "crm",
    descriptionKey: "crmDesc",
    matchPrefixes: ["/crm"],
    roleDefaultOrder: { sales: 1, manager: 9, admin: 10 },
  },
  {
    id: "leads",
    href: "/crm/leads",
    icon: GitBranch,
    group: "crm",
    permissions: ["crm.leads.view"],
    featureFlag: "crm",
    nameKey: "leads",
    descriptionKey: "leadsDesc",
    matchPrefixes: ["/crm/leads", "/crm/pipeline"],
    roleDefaultOrder: { sales: 2, manager: 10, admin: 11 },
  },
  {
    id: "quotations",
    href: "/crm/quotations",
    icon: FileText,
    group: "crm",
    permissions: ["crm.quotations.view"],
    featureFlag: "crm",
    nameKey: "quotations",
    descriptionKey: "quotationsDesc",
    matchPrefixes: ["/crm/quotations"],
    roleDefaultOrder: { sales: 5, manager: 11, admin: 12 },
  },
  {
    id: "followups",
    href: "/crm/follow-ups",
    icon: CalendarClock,
    group: "crm",
    permissions: ["crm.follow_ups.manage", "crm.leads.view"],
    featureFlag: "crm",
    nameKey: "followups",
    descriptionKey: "followupsDesc",
    matchPrefixes: ["/crm/follow-ups"],
    roleDefaultOrder: { sales: 6, manager: 12, admin: 13 },
  },
  {
    id: "inventory",
    href: "/inventory/dashboard",
    icon: Package,
    group: "inventory",
    permissions: ["inventory.dashboard.view", "inventory.stock.view", "inventory.products.view"],
    featureFlag: "inventory",
    nameKey: "inventory",
    descriptionKey: "inventoryDesc",
    matchPrefixes: ["/inventory/dashboard", "/inventory/products", "/inventory/receiving", "/inventory/counts", "/inventory/reservations"],
    roleDefaultOrder: { inventory: 1, manager: 13, admin: 14 },
  },
  {
    id: "equipment",
    href: "/inventory/equipment",
    icon: Cpu,
    group: "inventory",
    permissions: ["inventory.equipment.view"],
    featureFlag: "inventory",
    nameKey: "equipment",
    descriptionKey: "equipmentDesc",
    matchPrefixes: ["/inventory/equipment", "/customer-equipment", "/custody", "/assets", "/maintenance"],
    roleDefaultOrder: { inventory: 2, manager: 14, admin: 15 },
  },
  {
    id: "warehouses",
    href: "/inventory/stock",
    icon: Warehouse,
    group: "inventory",
    permissions: ["inventory.stock.view", "inventory.locations.view"],
    featureFlag: "inventory",
    nameKey: "warehouses",
    descriptionKey: "warehousesDesc",
    matchPrefixes: ["/inventory/stock"],
    roleDefaultOrder: { inventory: 3, manager: 15, admin: 16 },
  },
  {
    id: "transfers",
    href: "/inventory/transfers",
    icon: ArrowLeftRight,
    group: "inventory",
    permissions: ["inventory.transfers.view"],
    featureFlag: "inventory",
    nameKey: "transfers",
    descriptionKey: "transfersDesc",
    matchPrefixes: ["/inventory/transfers"],
    roleDefaultOrder: { inventory: 4, manager: 16, admin: 17 },
  },
  {
    id: "purchasing",
    href: "/purchasing/requests",
    icon: ShoppingCart,
    group: "inventory",
    permissions: ["inventory.purchasing.view"],
    featureFlag: "purchasing",
    nameKey: "purchasing",
    descriptionKey: "purchasingDesc",
    matchPrefixes: ["/purchasing", "/suppliers"],
    roleDefaultOrder: { inventory: 5, manager: 17, admin: 18 },
  },
  {
    id: "sites",
    href: "/sites",
    icon: RadioTower,
    group: "inventory",
    permissions: ["inventory.sites.view", "inventory.towers.view"],
    featureFlag: "sites",
    nameKey: "sites",
    descriptionKey: "sitesDesc",
    matchPrefixes: ["/sites", "/towers"],
    roleDefaultOrder: { inventory: 6, noc: 5, manager: 18, admin: 19 },
  },
  {
    id: "repairs",
    href: "/repairs",
    icon: Hammer,
    group: "inventory",
    permissions: ["inventory.repairs.manage", "inventory.equipment.view"],
    featureFlag: "assets",
    nameKey: "repairs",
    descriptionKey: "repairsDesc",
    matchPrefixes: ["/repairs"],
    roleDefaultOrder: { inventory: 7, manager: 19, admin: 20 },
  },
  {
    id: "whatsapp",
    href: "/whatsapp/inbox",
    icon: MessageCircle,
    group: "comms",
    permissions: ["whatsapp.view"],
    featureFlag: "whatsapp",
    nameKey: "whatsapp",
    descriptionKey: "whatsappDesc",
    matchPrefixes: ["/whatsapp"],
    roleDefaultOrder: { support: 6, sales: 7, manager: 20, admin: 21 },
  },
  {
    id: "notifications",
    href: "/notifications",
    icon: Bell,
    group: "comms",
    permissions: ["notifications.view"],
    nameKey: "notifications",
    descriptionKey: "notificationsDesc",
    matchPrefixes: ["/notifications", "/collector/notifications", "/alerts"],
    roleDefaultOrder: { collector: 3, support: 7, noc: 6, manager: 21, admin: 22 },
  },
  {
    id: "reports",
    href: "/reports/collection",
    icon: BarChart3,
    group: "insights",
    permissions: ["reports.assignments", "reports.visits", "reports.payments", "inventory.reports.view", "crm.reports.view"],
    nameKey: "reports",
    descriptionKey: "reportsDesc",
    matchPrefixes: ["/reports", "/management"],
    roleDefaultOrder: { manager: 22, admin: 2, sales: 8, inventory: 8 },
  },
  {
    id: "branches",
    href: "/branches",
    icon: Building2,
    group: "admin",
    permissions: ["branches.view", "branches.manage"],
    nameKey: "branches",
    descriptionKey: "branchesDesc",
    matchPrefixes: ["/branches"],
    roleDefaultOrder: { admin: 23, manager: 23 },
  },
  {
    id: "users",
    href: "/users",
    icon: UserCog,
    group: "admin",
    permissions: ["users.view", "users.manage"],
    nameKey: "users",
    descriptionKey: "usersDesc",
    matchPrefixes: ["/users", "/roles"],
    roleDefaultOrder: { admin: 24 },
  },
  {
    id: "audit",
    href: "/audit-logs",
    icon: ScrollText,
    group: "admin",
    permissions: ["audit.view"],
    nameKey: "audit",
    descriptionKey: "auditDesc",
    matchPrefixes: ["/audit-logs"],
    roleDefaultOrder: { admin: 25 },
  },
  {
    id: "settings",
    href: "/settings",
    icon: Settings,
    group: "admin",
    permissions: ["settings.manage"],
    nameKey: "settings",
    descriptionKey: "settingsDesc",
    matchPrefixes: ["/settings", "/zoho"],
    roleDefaultOrder: { admin: 26, manager: 24 },
  },
];

export const APP_GROUP_ORDER: AppGroupId[] = [
  "operations",
  "field",
  "finance",
  "crm",
  "inventory",
  "comms",
  "insights",
  "admin",
];

export function getAppById(id: string): CatalogApp | undefined {
  return APP_CATALOG.find((a) => a.id === id);
}

export function resolveAppFromPath(pathname: string): CatalogApp | undefined {
  const path = pathname.replace(/^\/(en|fa)(?=\/|$)/, "") || "/";
  let best: CatalogApp | undefined;
  let bestLen = -1;
  for (const app of APP_CATALOG) {
    for (const prefix of app.matchPrefixes) {
      if (path === prefix || path.startsWith(`${prefix}/`)) {
        if (prefix.length > bestLen) {
          best = app;
          bestLen = prefix.length;
        }
      }
    }
  }
  return best;
}

export function isAppVisible(
  app: CatalogApp,
  hasAnyPermission: (permissions: string[]) => boolean,
): boolean {
  if (app.featureFlag && !isModuleEnabled(app.featureFlag)) return false;
  if (app.permissions.length === 0) return true;
  return hasAnyPermission(app.permissions);
}

/** Map Spatie / display role names onto launcher personas. */
export function resolveLauncherRoles(roles: string[] | undefined | null): LauncherRole[] {
  const set = new Set<LauncherRole>();
  for (const raw of roles ?? []) {
    const r = raw.toLowerCase();
    if (r.includes("collector")) set.add("collector");
    if (r.includes("support") || r.includes("helpdesk")) set.add("support");
    if (r.includes("noc") || r.includes("network")) set.add("noc");
    if (r.includes("sales") || r.includes("crm")) set.add("sales");
    if (r.includes("inventory") || r.includes("warehouse") || r.includes("stock")) set.add("inventory");
    if (r.includes("manager") || r.includes("branch finance")) set.add("manager");
    if (r.includes("admin") || r.includes("auditor") || r.includes("central finance")) set.add("admin");
  }
  if (set.size === 0) set.add("admin");
  return Array.from(set);
}

export function sortAppsForRoles(apps: CatalogApp[], roles: LauncherRole[]): CatalogApp[] {
  return [...apps].sort((a, b) => {
    const orderA = Math.min(...roles.map((r) => a.roleDefaultOrder[r] ?? 999));
    const orderB = Math.min(...roles.map((r) => b.roleDefaultOrder[r] ?? 999));
    if (orderA !== orderB) return orderA - orderB;
    return a.id.localeCompare(b.id);
  });
}

/** In-app sidebar links for the current app context. */
export type AppNavItem = {
  href: string;
  labelKey: string;
  permissions: string[];
};

export const APP_CONTEXT_NAV: Record<string, AppNavItem[]> = {
  payments: [
    { href: "/payments", labelKey: "payments", permissions: ["payments.view"] },
    { href: "/receipts", labelKey: "receipts", permissions: ["receipts.view"] },
    { href: "/wallets", labelKey: "wallets", permissions: ["wallets.view"] },
    { href: "/handovers", labelKey: "cashHandovers", permissions: ["handovers.view", "handovers.review"] },
    { href: "/cashboxes", labelKey: "cashboxes", permissions: ["cashboxes.view"] },
    { href: "/transfers", labelKey: "transfers", permissions: ["cashbox_transfers.view"] },
    { href: "/payments/reversals", labelKey: "paymentReversals", permissions: ["reversals.request", "reversals.approve"] },
  ],
  collections: [
    { href: "/assignments", labelKey: "assignments", permissions: ["assignments.view"] },
    { href: "/visits", labelKey: "visits", permissions: ["visits.view"] },
    { href: "/routes", labelKey: "routes", permissions: ["routes.view"] },
    { href: "/promises", labelKey: "promises", permissions: ["promises.view"] },
    { href: "/debtors", labelKey: "debtors", permissions: ["debtors.view"] },
    { href: "/collectors", labelKey: "collectors", permissions: ["collectors.view", "collectors.manage"] },
    { href: "/collector", labelKey: "collectorHome", permissions: ["assignments.view", "visits.view"] },
  ],
  tasks: [
    { href: "/tasks/my", labelKey: "myTasks", permissions: ["tasks.view"] },
    { href: "/tasks", labelKey: "tasksQueue", permissions: ["tasks.view"] },
    { href: "/technical/dashboard", labelKey: "technicalDashboard", permissions: ["reports.technical", "tasks.view"] },
  ],
  support: [
    { href: "/tickets", labelKey: "tickets", permissions: ["tickets.view"] },
    { href: "/support/dashboard", labelKey: "supportDashboard", permissions: ["reports.support", "tickets.view"] },
  ],
  noc: [
    { href: "/noc/services", labelKey: "nocServices", permissions: ["services.noc.view", "services.view"] },
    { href: "/noc/dashboard", labelKey: "nocDashboard", permissions: ["escalations.view", "dashboard.view"] },
  ],
  services: [
    { href: "/services/dashboard", labelKey: "servicesDashboard", permissions: ["services.dashboard.view", "services.view"] },
    { href: "/services", labelKey: "servicesAll", permissions: ["services.view"] },
    { href: "/services/pending-installation", labelKey: "servicesPendingInstall", permissions: ["services.view"] },
    { href: "/services/pending-activation", labelKey: "servicesPendingActivation", permissions: ["services.view", "services.activate"] },
    { href: "/services/suspended", labelKey: "servicesSuspended", permissions: ["services.view", "services.suspend"] },
    { href: "/services/finance-hold", labelKey: "servicesFinanceHold", permissions: ["services.view", "services.finance_holds.manage"] },
    { href: "/services/change-requests", labelKey: "servicesChangeRequests", permissions: ["services.view", "services.change"] },
    { href: "/services/relocations", labelKey: "servicesRelocations", permissions: ["services.view", "services.relocate"] },
    { href: "/services/packages", labelKey: "servicesPackages", permissions: ["services.packages.view", "services.view"] },
    { href: "/services/contracts", labelKey: "servicesContracts", permissions: ["services.contracts.view", "services.view"] },
    { href: "/services/sla", labelKey: "servicesSla", permissions: ["services.sla.manage", "services.view"] },
    { href: "/services/reports", labelKey: "servicesReports", permissions: ["services.reports.view", "services.view"] },
  ],
  installations: [
    { href: "/installations", labelKey: "installations", permissions: ["installations.view"] },
  ],
  customers: [
    { href: "/customers", labelKey: "customers", permissions: ["customers.view"] },
    { href: "/invoices", labelKey: "invoices", permissions: ["invoices.view"] },
  ],
  crm: [
    { href: "/crm/dashboard", labelKey: "crmDashboard", permissions: ["crm.reports.view", "crm.leads.view"] },
    { href: "/crm/pipeline", labelKey: "crmPipeline", permissions: ["crm.leads.view", "crm.pipeline.manage"] },
    { href: "/crm/leads", labelKey: "crmLeads", permissions: ["crm.leads.view"] },
    { href: "/crm/follow-ups", labelKey: "crmFollowUps", permissions: ["crm.follow_ups.manage", "crm.leads.view"] },
    { href: "/crm/quotations", labelKey: "crmQuotations", permissions: ["crm.quotations.view"] },
    { href: "/crm/reports", labelKey: "crmReports", permissions: ["crm.reports.view"] },
  ],
  leads: [
    { href: "/crm/leads", labelKey: "crmLeads", permissions: ["crm.leads.view"] },
    { href: "/crm/pipeline", labelKey: "crmPipeline", permissions: ["crm.leads.view", "crm.pipeline.manage"] },
  ],
  quotations: [{ href: "/crm/quotations", labelKey: "crmQuotations", permissions: ["crm.quotations.view"] }],
  followups: [{ href: "/crm/follow-ups", labelKey: "crmFollowUps", permissions: ["crm.follow_ups.manage", "crm.leads.view"] }],
  inventory: [
    { href: "/inventory/dashboard", labelKey: "inventoryDashboard", permissions: ["inventory.dashboard.view", "dashboard.view"] },
    { href: "/inventory/products", labelKey: "inventoryProducts", permissions: ["inventory.products.view"] },
    { href: "/inventory/stock", labelKey: "inventoryStock", permissions: ["inventory.stock.view"] },
    { href: "/inventory/reservations", labelKey: "inventoryReservations", permissions: ["inventory.reservations.view"] },
    { href: "/inventory/receiving", labelKey: "inventoryReceiving", permissions: ["inventory.receiving.manage"] },
    { href: "/inventory/counts", labelKey: "inventoryCounts", permissions: ["inventory.stock.count", "inventory.stock.view"] },
  ],
  equipment: [
    { href: "/inventory/equipment", labelKey: "inventoryEquipment", permissions: ["inventory.equipment.view"] },
    { href: "/assets", labelKey: "inventoryAssets", permissions: ["inventory.assets.view"] },
    { href: "/customer-equipment", labelKey: "inventoryCustomerEquipment", permissions: ["inventory.equipment.view"] },
    { href: "/custody", labelKey: "inventoryCustody", permissions: ["inventory.custody.manage"] },
  ],
  warehouses: [{ href: "/inventory/stock", labelKey: "inventoryStock", permissions: ["inventory.stock.view"] }],
  transfers: [{ href: "/inventory/transfers", labelKey: "inventoryTransfers", permissions: ["inventory.transfers.view"] }],
  purchasing: [
    { href: "/purchasing/requests", labelKey: "purchaseRequests", permissions: ["inventory.purchasing.view"] },
    { href: "/purchasing/orders", labelKey: "purchaseOrders", permissions: ["inventory.purchasing.view"] },
    { href: "/suppliers", labelKey: "inventorySuppliers", permissions: ["inventory.suppliers.view"] },
  ],
  sites: [
    { href: "/sites", labelKey: "inventorySites", permissions: ["inventory.sites.view"] },
  ],
  repairs: [{ href: "/repairs", labelKey: "inventoryRepairs", permissions: ["inventory.repairs.manage"] }],
  whatsapp: [
    { href: "/whatsapp/inbox", labelKey: "whatsappInbox", permissions: ["whatsapp.view"] },
    { href: "/whatsapp/messages", labelKey: "whatsappMessages", permissions: ["whatsapp.view"] },
    { href: "/whatsapp/templates", labelKey: "whatsappTemplates", permissions: ["whatsapp.view"] },
  ],
  notifications: [
    { href: "/notifications", labelKey: "notifications", permissions: ["notifications.view"] },
    { href: "/alerts", labelKey: "alerts", permissions: ["zoho.view", "dashboard.view"] },
  ],
  dashboard: [{ href: "/dashboard", labelKey: "dashboard", permissions: ["dashboard.view"] }],
  reports: [
    { href: "/reports/collection", labelKey: "collectionReports", permissions: ["reports.assignments", "reports.visits"] },
    { href: "/reports/payments", labelKey: "paymentReports", permissions: ["reports.payments", "payments.view"] },
    { href: "/reports/inventory", labelKey: "inventoryReports", permissions: ["inventory.reports.view"] },
    { href: "/management/service-operations", labelKey: "managementOps", permissions: ["reports.management"] },
  ],
  branches: [{ href: "/branches", labelKey: "branches", permissions: ["branches.view", "branches.manage"] }],
  users: [
    { href: "/users", labelKey: "users", permissions: ["users.view", "users.manage"] },
    { href: "/roles", labelKey: "roles", permissions: ["roles.view"] },
  ],
  audit: [{ href: "/audit-logs", labelKey: "auditLogs", permissions: ["audit.view"] }],
  settings: [
    { href: "/settings", labelKey: "settings", permissions: ["settings.manage"] },
    { href: "/zoho", labelKey: "zohoStructure", permissions: ["zoho.view"] },
    { href: "/settings/system-version", labelKey: "systemVersion", permissions: ["dashboard.view"] },
  ],
};

/** Mobile bottom-nav candidates per persona (max 5 shown after permission filter). */
export const BOTTOM_NAV_BY_ROLE: Record<LauncherRole, string[]> = {
  collector: ["collections", "payments", "notifications", "tasks", "apps"],
  support: ["support", "tasks", "customers", "whatsapp", "apps"],
  noc: ["noc", "services", "tasks", "support", "apps"],
  sales: ["crm", "leads", "customers", "installations", "apps"],
  inventory: ["inventory", "warehouses", "transfers", "equipment", "apps"],
  manager: ["dashboard", "collections", "payments", "reports", "apps"],
  admin: ["dashboard", "reports", "users", "settings", "apps"],
};
