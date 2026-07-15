import { apiDownload, apiFetch, toQuery } from "@/lib/api";
import type {
  AssignmentBulkOperation,
  AssignmentHistoryEntry,
  AssignmentListParams,
  AutoAssignPreviewPayload,
  BulkAssignPayload,
  CollectorWorkload,
  CreateAssignmentPayload,
  Customer,
  CustomerAssignment,
  ReassignPayload,
} from "@/lib/types";

export async function listAssignments(params: AssignmentListParams = {}) {
  return apiFetch<CustomerAssignment[]>(
    `/assignments${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
      collector_id: params.collector_id,
      branch_id: params.branch_id,
      is_active: params.is_active,
      customer_id: params.customer_id,
    })}`,
  );
}

export async function getAssignment(id: number) {
  return apiFetch<CustomerAssignment>(`/assignments/${id}`);
}

export async function createAssignment(payload: CreateAssignmentPayload) {
  return apiFetch<{ assignment: CustomerAssignment; warnings: string[] }>(
    "/assignments",
    { method: "POST", body: JSON.stringify(payload) },
  );
}

export async function bulkAssign(payload: BulkAssignPayload) {
  return apiFetch<unknown>("/assignments/bulk", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function reassignAssignment(id: number, payload: ReassignPayload) {
  return apiFetch<CustomerAssignment>(`/assignments/${id}/reassign`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function cancelAssignment(id: number, reason: string) {
  return apiFetch<CustomerAssignment>(`/assignments/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function acceptAssignment(id: number) {
  return apiFetch<CustomerAssignment>(`/assignments/${id}/accept`, {
    method: "POST",
  });
}

export async function markAssignmentViewed(id: number) {
  return apiFetch<CustomerAssignment>(`/assignments/${id}/viewed`, {
    method: "POST",
  });
}

export async function getAssignmentHistory(id: number) {
  return apiFetch<AssignmentHistoryEntry[]>(`/assignments/${id}/history`);
}

export async function exportAssignments(params: AssignmentListParams = {}) {
  await apiDownload(
    `/assignments/export${toQuery({
      status: params.status,
      branch_id: params.branch_id,
    })}`,
    "assignments-export.csv",
  );
}

export async function listUnassignedDebtors(params: {
  page?: number;
  per_page?: number;
  branch_id?: number | string;
} = {}) {
  return apiFetch<Customer[]>(
    `/assignments/unassigned-debtors${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      branch_id: params.branch_id,
    })}`,
  );
}

export async function autoAssignPreview(payload: AutoAssignPreviewPayload) {
  return apiFetch<AssignmentBulkOperation>("/assignments/auto-preview", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function autoAssignConfirm(operationId: number) {
  return apiFetch<unknown>("/assignments/auto-confirm", {
    method: "POST",
    body: JSON.stringify({ operation_id: operationId }),
  });
}

export async function getCollectorsWorkload(branchId?: number | string) {
  return apiFetch<CollectorWorkload[]>(
    `/collectors/workload${toQuery({ branch_id: branchId })}`,
  );
}
