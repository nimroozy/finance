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
 * Stage 6 WhatsApp foundation is enabled.
 * Later modules remain placeholders until their stage ships.
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
    enabled: false,
    stage: 7,
    showRoadmapPlaceholder: true,
  },
  tasks: {
    id: "tasks",
    enabled: false,
    stage: 7,
    showRoadmapPlaceholder: true,
  },
  crm: {
    id: "crm",
    enabled: false,
    stage: 8,
    showRoadmapPlaceholder: true,
  },
  installations: {
    id: "installations",
    enabled: false,
    stage: 8,
    showRoadmapPlaceholder: true,
  },
  inventory: {
    id: "inventory",
    enabled: false,
    stage: 9,
    showRoadmapPlaceholder: true,
  },
  assets: {
    id: "assets",
    enabled: false,
    stage: 9,
    showRoadmapPlaceholder: true,
  },
  sites: {
    id: "sites",
    enabled: false,
    stage: 9,
    showRoadmapPlaceholder: true,
  },
  radius: {
    id: "radius",
    enabled: false,
    stage: 10,
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
