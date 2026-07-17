import { ApiError, apiFetch, toQuery } from "@/lib/api";
import type { PaginationMeta } from "@/lib/types";

export const COMMERCIAL_STATUSES = [
  "draft",
  "pending_activation",
  "active",
  "suspended",
  "pending_cancellation",
  "cancelled",
  "expired",
] as const;

export const OPERATIONAL_STATUSES = [
  "not_provisioned",
  "pending_install",
  "ready",
  "online",
  "offline",
  "suspended",
  "decommissioned",
] as const;

export const BILLING_STATUSES = [
  "not_billed",
  "current",
  "overdue",
  "on_hold",
  "closed",
] as const;

export const ACTIVATION_CHECKLIST = [
  "zoho_linked",
  "installation_completed",
  "inventory_reconciled",
  "equipment_assigned",
] as const;

export type CommercialStatus = (typeof COMMERCIAL_STATUSES)[number];
export type OperationalStatus = (typeof OPERATIONAL_STATUSES)[number];
export type BillingStatus = (typeof BILLING_STATUSES)[number];

export type ServiceCustomerRef = {
  id: number;
  contact_name?: string | null;
  customer_number?: string | null;
  company_name?: string | null;
};

export type ServicePackageRef = {
  id: number;
  code: string;
  name: string;
  monthly_price?: number | string | null;
  download_mbps?: number | null;
  upload_mbps?: number | null;
};

export type ServiceLocationRef = {
  id: number;
  name: string;
  address?: string | null;
  city?: string | null;
};

export type IspService = {
  id: number;
  service_number: string;
  customer_id: number;
  branch_id: number;
  service_location_id?: number | null;
  installation_id?: number | null;
  package_id?: number | null;
  package_version_id?: number | null;
  service_type_code?: string | null;
  access_technology_code?: string | null;
  billing_frequency?: string | null;
  mrr?: number | string | null;
  installation_fee?: number | string | null;
  currency?: string | null;
  commercial_status: string;
  operational_status: string;
  billing_status: string;
  ownership_status?: string | null;
  activation_date?: string | null;
  suspension_date?: string | null;
  cancellation_date?: string | null;
  expiration_date?: string | null;
  sla_template_id?: number | null;
  notes?: string | null;
  circuit_id?: string | null;
  customer_reference?: string | null;
  network_node?: string | null;
  vlan?: string | null;
  static_ip?: string | null;
  public_ip?: string | null;
  private_ip?: string | null;
  pppoe_username_placeholder?: string | null;
  tower_id?: number | null;
  site_id?: number | null;
  customer?: ServiceCustomerRef | null;
  package?: ServicePackageRef | null;
  package_version?: Record<string, unknown> | null;
  location?: ServiceLocationRef | null;
  installation?: Record<string, unknown> | null;
  sla?: Record<string, unknown> | null;
  sla_template?: Record<string, unknown> | null;
  equipment_summary?: {
    total: number;
    installed: number;
    returned: number;
    by_status: Record<string, number>;
  };
  technical_owner_id?: number | null;
  sales_owner_id?: number | null;
  support_owner_id?: number | null;
  zoho_customer_id?: string | null;
  start_date?: string | null;
  reactivation_date?: string | null;
  allowed_commercial_transitions?: string[];
  allowed_operational_transitions?: string[];
  created_at?: string;
  updated_at?: string;
};

export type ServiceDashboard = {
  total: number;
  by_commercial_status: Record<string, number>;
  by_operational_status: Record<string, number>;
  active_mrr: number | string;
  suspended: number;
  pending_activation: number;
  expiring_30_days: number;
  radius_enabled: boolean;
};

export type ServiceNocWorkspace = {
  attention_queue: IspService[];
  pending_activation: IspService[];
  open_change_requests?: Array<{ id: number; service_id: number; status: string; service?: IspService | null }>;
  open_relocations?: Array<{ id: number; service_id: number; status: string; service?: IspService | null }>;
  radius_automation: boolean;
  note?: string;
};

export type ServiceStatusReport = {
  commercial: Array<{ commercial_status: string; count: number; mrr?: number | string }>;
  operational: Array<{ operational_status: string; count: number }>;
  billing: Array<{ billing_status: string; count: number }>;
  by_type: Array<{ service_type_code: string | null; count: number }>;
};

export type ServiceBillingView = {
  service_id: number;
  service_number: string;
  customer_id: number;
  zoho_customer_id?: string | null;
  billing_status: string;
  mrr?: number | string | null;
  source_of_truth: string;
  read_only: boolean;
  invoices: Array<Record<string, unknown>>;
  payments: Array<Record<string, unknown>>;
  sync_timestamps?: Record<string, string | null>;
  note?: string;
};

export type ServiceTimelineEvent = {
  id: string;
  type: string;
  occurred_at?: string | null;
  actor?: { id: number; name?: string | null } | null;
  title: string;
  summary?: string | null;
  meta?: Record<string, unknown>;
  /** @deprecated legacy shape */
  at?: string | null;
  data?: Record<string, unknown>;
};

export type ServiceChangeRequest = {
  id: number;
  service_id: number;
  type?: string | null;
  status: string;
  reason?: string | null;
  technical_status?: string | null;
  finance_status?: string | null;
  requested_values?: Record<string, unknown>;
  service?: IspService | null;
  created_at?: string;
};

export type ServiceRelocation = {
  id: number;
  service_id: number;
  status: string;
  old_location_id?: number | null;
  new_location_id?: number | null;
  notes?: string | null;
  service?: IspService | null;
  created_at?: string;
};

export type ActivationChecklist = {
  checklist: Record<string, boolean>;
  missing: string[];
  evidence: Record<string, Record<string, unknown>>;
};

export type ServiceType = {
  id: number;
  code: string;
  name: string;
  is_active?: boolean;
};

export type ServiceAccessTechnology = {
  id: number;
  code: string;
  name: string;
  is_active?: boolean;
};

export type ServiceSlaTemplate = {
  id: number;
  code?: string | null;
  name: string;
  response_minutes?: number | null;
  resolution_minutes?: number | null;
  resolve_minutes?: number | null;
  is_active?: boolean;
};

export type ServicePackageVersion = {
  id: number;
  package_id: number;
  version?: number | string | null;
  monthly_price?: number | string | null;
  installation_fee?: number | string | null;
  download_mbps?: number | null;
  upload_mbps?: number | null;
  effective_date?: string | null;
  is_current?: boolean;
};

export type ServicePackage = {
  id: number;
  code: string;
  name: string;
  branch_id?: number | null;
  service_type_code: string;
  access_technology_code: string;
  download_mbps?: number | null;
  upload_mbps?: number | null;
  monthly_price?: number | string | null;
  installation_fee?: number | string | null;
  billing_frequency?: string | null;
  contract_period_months?: number | null;
  grace_period_days?: number | null;
  sla_template_id?: number | null;
  is_active?: boolean;
  effective_date?: string | null;
  versions?: ServicePackageVersion[];
};

export type ServiceLocation = {
  id: number;
  customer_id?: number | null;
  branch_id?: number | null;
  name: string;
  address?: string | null;
  city?: string | null;
  district?: string | null;
  gps_lat?: number | string | null;
  gps_lng?: number | string | null;
  tower_id?: number | null;
  site_id?: number | null;
  notes?: string | null;
};

export type ServiceContract = {
  id: number;
  contract_number?: string | null;
  customer_id: number;
  branch_id: number;
  service_id?: number | null;
  status?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  renewal_type?: string | null;
  setup_fee?: number | string | null;
  recurring_fee?: number | string | null;
  zoho_reference?: string | null;
};

export type ServiceFinanceHold = {
  id: number;
  service_id: number;
  status?: string | null;
  hold_type?: string | null;
  reason?: string | null;
  effective_at?: string | null;
  released_at?: string | null;
};

type ListParams = Record<string, string | number | boolean | undefined | null>;

export async function listServices(params: ListParams = {}) {
  return apiFetch<IspService[]>(`/services${toQuery(params)}`);
}

export async function getService(id: number | string) {
  return apiFetch<IspService>(`/services/${id}`);
}

export async function createService(payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function transitionService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}/transition`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function activateService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}/activate`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function suspendService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}/suspend`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function reactivateService(id: number | string, payload: Record<string, unknown> = {}) {
  return apiFetch<IspService>(`/services/${id}/reactivate`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function cancelService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createChangeRequest(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<Record<string, unknown>>(`/services/${id}/change-requests`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function relocateService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService | Record<string, unknown>>(`/services/${id}/relocations`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function renewService(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<Record<string, unknown>>(`/services/${id}/renewals`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function placeFinanceHold(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<ServiceFinanceHold>(`/services/${id}/finance-holds`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function releaseFinanceHold(holdId: number | string) {
  return apiFetch<ServiceFinanceHold>(`/service-finance-holds/${holdId}/release`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function getServiceBilling(id: number | string) {
  return apiFetch<ServiceBillingView>(`/services/${id}/billing`);
}

export async function getServiceTimeline(id: number | string) {
  return apiFetch<ServiceTimelineEvent[]>(`/services/${id}/timeline`);
}

export async function getServicesDashboard(branchId?: string | number) {
  return apiFetch<ServiceDashboard>(
    `/services/dashboard${toQuery({ branch_id: branchId || undefined })}`,
  );
}

export async function getServicesNocWorkspace(branchId?: string | number) {
  return apiFetch<ServiceNocWorkspace>(
    `/services/noc-workspace${toQuery({ branch_id: branchId || undefined })}`,
  );
}

export async function getServicesStatusReport(branchId?: string | number) {
  return apiFetch<ServiceStatusReport>(
    `/services/reports/status${toQuery({ branch_id: branchId || undefined })}`,
  );
}

export async function listServicePackages(params: ListParams = {}) {
  return apiFetch<ServicePackage[]>(`/service-packages${toQuery(params)}`);
}

export async function getServicePackage(id: number | string) {
  const res = await listServicePackages({ per_page: 100 });
  const found = res.data.find((p) => p.id === Number(id));
  if (!found) {
    throw new ApiError("Package not found", 404);
  }
  return { success: true as const, data: found, meta: res.meta as PaginationMeta | undefined, message: null };
}

export async function createServicePackage(payload: Record<string, unknown>) {
  return apiFetch<ServicePackage>(`/service-packages`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createServicePackageVersion(
  id: number | string,
  payload: Record<string, unknown>,
) {
  return apiFetch<ServicePackageVersion>(`/service-packages/${id}/versions`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listServiceLocations(params: ListParams = {}) {
  return apiFetch<ServiceLocation[]>(`/service-locations${toQuery(params)}`);
}

export async function createServiceLocation(payload: Record<string, unknown>) {
  return apiFetch<ServiceLocation>(`/service-locations`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listServiceTypes() {
  return apiFetch<ServiceType[]>(`/service-types`);
}

export async function listServiceAccessTechnologies() {
  return apiFetch<ServiceAccessTechnology[]>(`/service-access-technologies`);
}

export async function listServiceSlaTemplates() {
  return apiFetch<ServiceSlaTemplate[]>(`/service-sla-templates`);
}

export async function listServiceContracts(params: ListParams = {}) {
  return apiFetch<ServiceContract[]>(`/service-contracts${toQuery(params)}`);
}

export async function createServiceContract(payload: Record<string, unknown>) {
  return apiFetch<ServiceContract>(`/service-contracts`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function convertInstallationToService(
  installationId: number | string,
  payload: Record<string, unknown> = {},
) {
  return apiFetch<IspService>(`/installations/${installationId}/convert-to-service`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function getActivationChecklist(id: number | string) {
  return apiFetch<ActivationChecklist>(`/services/${id}/activation-checklist`);
}

export async function confirmServiceOnline(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<IspService>(`/services/${id}/noc-confirm-online`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listChangeRequests(params: ListParams = {}) {
  return apiFetch<ServiceChangeRequest[]>(`/service-change-requests${toQuery(params)}`);
}

export async function advanceChangeRequest(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<ServiceChangeRequest>(`/service-change-requests/${id}/advance`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listRelocations(params: ListParams = {}) {
  return apiFetch<ServiceRelocation[]>(`/service-relocations${toQuery(params)}`);
}

export async function startRelocation(id: number | string) {
  return apiFetch<ServiceRelocation>(`/service-relocations/${id}/start`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function completeRelocation(id: number | string) {
  return apiFetch<IspService>(`/service-relocations/${id}/complete`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function listFinanceHolds(params: ListParams = {}) {
  return apiFetch<ServiceFinanceHold[]>(`/service-finance-holds${toQuery(params)}`);
}

export async function approveCancellation(id: number | string) {
  return apiFetch<Record<string, unknown>>(`/service-cancellations/${id}/approve`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function recoverCancellationEquipment(id: number | string, payload: Record<string, unknown> = {}) {
  return apiFetch<Record<string, unknown>>(`/service-cancellations/${id}/recover-equipment`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function completeCancellation(id: number | string) {
  return apiFetch<IspService>(`/service-cancellations/${id}/complete`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function updateServiceContract(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<ServiceContract>(`/service-contracts/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function createServiceSlaTemplate(payload: Record<string, unknown>) {
  return apiFetch<ServiceSlaTemplate>(`/service-sla-templates`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateServiceSlaTemplate(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<ServiceSlaTemplate>(`/service-sla-templates/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function deactivateServiceSlaTemplate(id: number | string) {
  return apiFetch<ServiceSlaTemplate>(`/service-sla-templates/${id}/deactivate`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}
