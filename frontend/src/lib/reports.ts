import { apiDownload, apiFetch, toQuery } from "@/lib/api";
import type { AssignmentsByCollectorRow } from "@/lib/types";

export async function reportAssignmentsByCollector(branchId?: number | string) {
  return apiFetch<AssignmentsByCollectorRow[]>(
    `/reports/assignments-by-collector${toQuery({ branch_id: branchId })}`,
  );
}

export async function reportVisitsByOutcome(branchId?: number | string) {
  return apiFetch<unknown[]>(
    `/reports/visits-by-outcome${toQuery({ branch_id: branchId })}`,
  );
}

export async function reportOverduePromises(branchId?: number | string) {
  return apiFetch<unknown[]>(
    `/reports/overdue-promises${toQuery({ branch_id: branchId })}`,
  );
}

export async function reportUnassignedDebtors(branchId?: number | string) {
  return apiFetch<unknown[]>(
    `/reports/unassigned-debtors${toQuery({ branch_id: branchId })}`,
  );
}

export async function reportGpsMismatch(branchId?: number | string) {
  return apiFetch<unknown[]>(
    `/reports/gps-mismatch${toQuery({ branch_id: branchId })}`,
  );
}

export async function exportReport(
  path:
    | "assignments-by-collector"
    | "visits-by-outcome"
    | "overdue-promises"
    | "unassigned-debtors"
    | "gps-mismatch",
  branchId?: number | string,
) {
  await apiDownload(
    `/reports/${path}${toQuery({ branch_id: branchId, export: "csv" })}`,
    `${path}.csv`,
  );
}
