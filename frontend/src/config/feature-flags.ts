/**
 * Feature flags for upcoming ISP platform modules.
 * Disabled modules must not appear as functional product pages.
 */
export type PlatformModule =
  | "whatsapp"
  | "ticketing"
  | "tasks"
  | "crm"
  | "installations"
  | "inventory"
  | "assets"
  | "sites"
  | "serviceLifecycle"
  | "radius"
  | "purchasing"
  | "unifiedDashboards";

export type ModuleFlag = {
  id: PlatformModule;
  /** When false, hide functional nav; may still show roadmap placeholder. */
  enabled: boolean;
  /** Stage that delivers the module. */
  stage: number;
  /** Show muted "coming soon" entry in roadmap nav group. */
  showRoadmapPlaceholder: boolean;
};

/**
 * Stage 6–10 modules are enabled (WhatsApp, ticketing/tasks/install queue, CRM, inventory/assets/sites, service lifecycle).
 * Radius remains a deferred placeholder (Stage 12).
 */
export const FEATURE_FLAGS: Record<PlatformModule, ModuleFlag> = {
  whatsapp: {
    id: "whatsapp",
    enabled: true,
    stage: 6,
    showRoadmapPlaceholder: false,
  },
  ticketing: {
    id: "ticketing",
    enabled: true,
    stage: 7,
    showRoadmapPlaceholder: false,
  },
  tasks: {
    id: "tasks",
    enabled: true,
    stage: 7,
    showRoadmapPlaceholder: false,
  },
  crm: {
    id: "crm",
    enabled: true,
    stage: 8,
    showRoadmapPlaceholder: false,
  },
  installations: {
    id: "installations",
    enabled: true,
    stage: 7,
    showRoadmapPlaceholder: false,
  },
  inventory: {
    id: "inventory",
    enabled: true,
    stage: 9,
    showRoadmapPlaceholder: false,
  },
  assets: {
    id: "assets",
    enabled: true,
    stage: 9,
    showRoadmapPlaceholder: false,
  },
  sites: {
    id: "sites",
    enabled: true,
    stage: 9,
    showRoadmapPlaceholder: false,
  },
  serviceLifecycle: {
    id: "serviceLifecycle",
    enabled: true,
    stage: 10,
    showRoadmapPlaceholder: false,
  },
  radius: {
    id: "radius",
    enabled: false,
    stage: 12,
    showRoadmapPlaceholder: true,
  },
  purchasing: {
    id: "purchasing",
    enabled: false,
    stage: 11,
    showRoadmapPlaceholder: true,
  },
  unifiedDashboards: {
    id: "unifiedDashboards",
    enabled: false,
    stage: 11,
    showRoadmapPlaceholder: true,
  },
};

export function isModuleEnabled(id: PlatformModule): boolean {
  return FEATURE_FLAGS[id]?.enabled === true;
}

export function roadmapPlaceholders(): ModuleFlag[] {
  return Object.values(FEATURE_FLAGS).filter((f) => f.showRoadmapPlaceholder && !f.enabled);
}
