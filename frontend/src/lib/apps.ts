import { apiFetch, toQuery } from "@/lib/api";

export type AppCounts = {
  tasks?: number;
  support?: number;
  installations?: number;
  services?: number;
  noc?: number;
  [key: string]: number | undefined;
};

export async function getAppCounts(branchId?: number | string) {
  return apiFetch<AppCounts>(`/apps/counts${toQuery({ branch_id: branchId })}`);
}
