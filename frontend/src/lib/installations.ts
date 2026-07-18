import { apiFetch, toQuery } from "@/lib/api";

export const INSTALLATION_STATUSES = [
  "request_received",
  "customer_verification",
  "coverage_check",
  "site_survey_pending",
  "finance_review",
  "equipment_waiting",
  "scheduled",
  "assigned",
  "travelling",
  "installing",
  "noc_activation_pending",
  "customer_confirmation",
  "completed",
  "delayed",
  "cancelled",
] as const;

export type InstallationStatus = (typeof INSTALLATION_STATUSES)[number];

export type Installation = {
  id: number;
  installation_number: string;
  branch_id: number;
  status: string;
  customer_id?: number | null;
  prospect_name?: string | null;
  contact_name?: string | null;
  phone?: string | null;
  address?: string | null;
  gps_lat?: number | null;
  gps_lng?: number | null;
  requested_package?: string | null;
  requested_date?: string | null;
  technician_id?: number | null;
  salesperson_id?: number | null;
  equipment_status?: string | null;
  notes?: string | null;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
  tasks?: Array<{
    id: number;
    task_number?: string;
    title?: string;
    status?: string;
  }>;
};

export type InstallationListParams = {
  page?: number;
  per_page?: number;
  status?: string;
};

export type CreateInstallationPayload = {
  branch_id: number;
  customer_id?: number;
  prospect_name?: string;
  contact_name?: string;
  phone?: string;
  address?: string;
  gps_lat?: number;
  gps_lng?: number;
  requested_package?: string;
  requested_date?: string;
  notes?: string;
  template_code?: string;
  expand_template?: boolean;
};

export async function listInstallations(params: InstallationListParams = {}) {
  return apiFetch<Installation[]>(
    `/installations${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
    })}`,
  );
}

export async function getInstallation(id: number | string) {
  return apiFetch<Installation>(`/installations/${id}`);
}

export async function createInstallation(payload: CreateInstallationPayload) {
  return apiFetch<Installation>("/installations", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function transitionInstallation(
  id: number | string,
  status: string,
  notes?: string,
) {
  return apiFetch<Installation>(`/installations/${id}/transition`, {
    method: "POST",
    body: JSON.stringify({ status, notes }),
  });
}
