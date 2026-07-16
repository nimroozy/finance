import { apiFetch, toQuery } from "@/lib/api";
import type {
  Branch,
  ZohoApiLog,
  ZohoAutoMatchResult,
  ZohoBranchMapping,
  ZohoBranchMappingPayload,
  ZohoCircuitBreaker,
  ZohoDataCenter,
  ZohoHealth,
  ZohoLocation,
  ZohoOrganization,
  ZohoPaymentMode,
  ZohoReportingTag,
  ZohoReportingTagMapping,
  ZohoStatus,
  ZohoSyncJob,
  ZohoSyncType,
} from "@/lib/types";

export async function getZohoStatus() {
  return apiFetch<ZohoStatus>("/zoho/status");
}

export async function getZohoHealth() {
  return apiFetch<ZohoHealth>("/zoho/health");
}

export async function listZohoLocations() {
  return apiFetch<ZohoLocation[]>("/zoho/locations");
}

export async function listZohoReportingTags() {
  return apiFetch<ZohoReportingTag[]>("/zoho/reporting-tags");
}

export async function listZohoPaymentModes() {
  return apiFetch<ZohoPaymentMode[]>("/zoho/payment-modes");
}

export async function getZohoDataCenters() {
  return apiFetch<ZohoDataCenter[]>("/zoho/data-centers");
}

export async function getZohoOauthRedirect(dataCenter?: string) {
  return apiFetch<{ authorize_url: string; state: string }>(
    `/zoho/oauth/redirect${toQuery({ data_center: dataCenter })}`,
  );
}

export async function connectZoho(dataCenter?: string) {
  const res = await getZohoOauthRedirect(dataCenter);
  if (typeof window !== "undefined") {
    window.location.href = res.data.authorize_url;
  }
  return res.data;
}

export async function disconnectZoho() {
  return apiFetch<{ disconnected: boolean }>("/zoho/disconnect", {
    method: "POST",
  });
}

export async function listZohoOrganizations() {
  return apiFetch<ZohoOrganization[]>("/zoho/organizations");
}

export async function selectZohoOrganization(zohoOrgId: string) {
  return apiFetch<{
    organization_id: string | null;
    organization_name: string | null;
  }>("/zoho/organizations/select", {
    method: "POST",
    body: JSON.stringify({ zoho_org_id: zohoOrgId }),
  });
}

export async function testZohoConnection() {
  return apiFetch<Record<string, unknown>>("/zoho/test", { method: "POST" });
}

export async function syncZohoStructure() {
  return apiFetch<{ queued: boolean; sync_job_id: number }>(
    "/zoho/structure/sync",
    { method: "POST" },
  );
}

export async function triggerZohoSync(
  type: ZohoSyncType,
  incremental = true,
) {
  return apiFetch<{
    queued: boolean;
    type: string;
    sync_job_id: number | null;
    sync_job_ids: number[];
  }>(`/zoho/sync/${type}`, {
    method: "POST",
    body: JSON.stringify({ incremental }),
  });
}

export async function listZohoSyncJobs(page = 1, filters?: {
  type?: string;
  status?: string;
  per_page?: number;
}) {
  return apiFetch<ZohoSyncJob[]>(
    `/zoho/sync-jobs${toQuery({
      page,
      per_page: filters?.per_page ?? 15,
      type: filters?.type,
      status: filters?.status,
    })}`,
  );
}

export async function getZohoSyncJob(id: number) {
  return apiFetch<ZohoSyncJob>(`/zoho/sync-jobs/${id}`);
}

export async function retryZohoSyncJob(id: number) {
  return apiFetch<{ queued: boolean; sync_job_id: number }>(
    `/zoho/sync-jobs/${id}/retry`,
    { method: "POST" },
  );
}

export async function listZohoApiLogs(page = 1, perPage = 25) {
  return apiFetch<ZohoApiLog[]>(
    `/zoho/api-logs${toQuery({ page, per_page: perPage })}`,
  );
}

export async function listZohoBranchMappings() {
  return apiFetch<ZohoBranchMapping[]>("/zoho/branch-mappings");
}

export async function previewZohoAutoMatch() {
  return apiFetch<ZohoAutoMatchResult[]>(
    "/zoho/branch-mappings/preview-auto-match",
    { method: "POST" },
  );
}

export async function applyZohoAutoMatch() {
  return apiFetch<{ applied: number }>(
    "/zoho/branch-mappings/apply-auto-match",
    {
      method: "POST",
      body: JSON.stringify({ confirmed: true }),
    },
  );
}

export async function linkBranchLocation(
  branchId: number,
  zohoLocationId: string,
) {
  return apiFetch<Branch>(`/zoho/branches/${branchId}/link-location`, {
    method: "POST",
    body: JSON.stringify({ zoho_location_id: zohoLocationId }),
  });
}

export async function importZohoLocationAsBranch(zohoId: string) {
  return apiFetch<Branch>(`/zoho/locations/${zohoId}/import-as-branch`, {
    method: "POST",
  });
}

export async function resumeZohoCircuit(type: string) {
  return apiFetch<ZohoCircuitBreaker>(
    `/zoho/circuit-breakers/${encodeURIComponent(type)}/resume`,
    { method: "POST" },
  );
}

export async function cleanupZohoFailedJobs(apply: boolean) {
  return apiFetch<{ output: string; applied: boolean }>(
    "/zoho/failed-jobs/cleanup",
    {
      method: "POST",
      body: JSON.stringify({ apply, only_timestamp: true }),
    },
  );
}

export async function createZohoBranchMapping(
  payload: ZohoBranchMappingPayload,
) {
  return apiFetch<ZohoBranchMapping>("/zoho/branch-mappings", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateZohoBranchMapping(
  id: number,
  payload: Partial<Omit<ZohoBranchMappingPayload, "branch_id">>,
) {
  return apiFetch<ZohoBranchMapping>(`/zoho/branch-mappings/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function deleteZohoBranchMapping(id: number) {
  return apiFetch<{ deleted: boolean }>(`/zoho/branch-mappings/${id}`, {
    method: "DELETE",
  });
}

export async function listZohoReportingTagMappings() {
  return apiFetch<ZohoReportingTagMapping[]>("/zoho/reporting-tag-mappings");
}

export async function upsertZohoReportingTagMappings(
  mappings: Array<{
    tag_id: string;
    tag_option_id: string;
    tag_name: string;
    option_name: string;
    branch_id?: number | null;
  }>,
) {
  return apiFetch<ZohoReportingTagMapping[]>("/zoho/reporting-tag-mappings", {
    method: "PUT",
    body: JSON.stringify({ mappings }),
  });
}
