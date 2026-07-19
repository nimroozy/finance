import { apiFetch, toQuery } from "@/lib/api";
import type { ApiSuccessResponse } from "@/lib/types";

export type CashHandover = {
  id: number;
  uuid: string;
  handover_number?: string | null;
  collector_id: number;
  branch_id: number;
  cashbox_id?: number | null;
  currency: string;
  declared_amount: string;
  selected_payment_total: string;
  counted_amount?: string | null;
  approved_amount?: string | null;
  variance_amount?: string | null;
  status: string;
  submitted_at?: string | null;
  reviewed_at?: string | null;
  approved_at?: string | null;
  rejected_at?: string | null;
  collector?: { id: number; user?: { id: number; name: string } | null } | null;
  cashbox?: { id: number; name: string } | null;
  branch?: { id: number; code: string; name_en: string; name_fa?: string | null } | null;
  items?: Array<{
    id: number;
    payment_id: number;
    payment_amount: string;
    approved_amount?: string | null;
    item_status: string;
  }>;
};

export type BranchCashbox = {
  id: number;
  branch_id: number;
  name: string;
  currency: string;
  balance: string;
  status?: string;
};

export async function listEligibleHandoverPayments(branchId: number, currency = "AFN") {
  return apiFetch<
    Array<{ id: number; amount: string; payment_reference?: string; confirmed_at?: string }>
  >(`/cash-handovers/eligible${toQuery({ branch_id: branchId, currency })}`);
}

export async function listCashHandovers(page = 1) {
  const response = await apiFetch<CashHandover[] | { data: CashHandover[] }>(
    `/cash-handovers${toQuery({ page, per_page: 20 })}`,
  );
  return {
    ...response,
    data: unwrapListData<CashHandover>(response.data),
  };
}

export async function getCashHandover(id: number) {
  return apiFetch<CashHandover>(`/cash-handovers/${id}`);
}

export async function draftCashHandover(payload: {
  branch_id: number;
  payment_ids: number[];
  declared_amount: string;
  notes?: string;
  currency?: string;
}) {
  return apiFetch<CashHandover>("/cash-handovers/draft", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function submitCashHandover(id: number) {
  return apiFetch<CashHandover>(`/cash-handovers/${id}/submit`, {
    method: "POST",
    body: "{}",
  });
}

export async function approveCashHandover(
  id: number,
  payload: { counted_amount: string; approved_amount?: string; notes?: string },
) {
  return apiFetch<CashHandover>(`/cash-handovers/${id}/approve`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function rejectCashHandover(id: number, notes: string) {
  return apiFetch<CashHandover>(`/cash-handovers/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ notes }),
  });
}

export function unwrapListData<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data as T[];
  if (
    data &&
    typeof data === "object" &&
    Array.isArray((data as { data?: unknown }).data)
  ) {
    return (data as { data: T[] }).data;
  }
  return [];
}

export async function listCashboxes(branchId?: number) {
  const response = await apiFetch<BranchCashbox[] | { data: BranchCashbox[] }>(
    `/cashboxes${toQuery({ branch_id: branchId, per_page: 100 })}`,
  );
  return {
    ...response,
    data: unwrapListData<BranchCashbox>(response.data),
  };
}

export type HandoverListResponse = ApiSuccessResponse<CashHandover[]>;
