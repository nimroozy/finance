import { apiFetch, toQuery } from "@/lib/api";

export type CustodyReversalConflict = {
  id: number;
  payment_id: number;
  handover_id: number | null;
  reversal_id: number | null;
  branch_id: number;
  status: string;
  reason: string;
  zoho_void_status?: string | null;
  zoho_void_error?: string | null;
  cashbox_insufficient?: boolean;
  critical_alert?: boolean;
  approved_amount?: string | number | null;
  payment?: {
    id: number;
    uuid: string;
    payment_reference?: string | null;
    amount: string | number;
    currency: string;
    status: string;
    handover_status?: string | null;
    zoho_payment_id?: string | null;
  } | null;
  handover?: {
    id: number;
    handover_number?: string | null;
    status: string;
    approved_amount?: string | number | null;
  } | null;
  requester?: { id: number; name: string } | null;
  reviewer?: { id: number; name: string } | null;
};

export type CustodyReversalDetail = {
  conflict: CustodyReversalConflict;
  payment: CustodyReversalConflict["payment"];
  receipt: { id: number; uuid?: string; status?: string; receipt_number?: string } | null;
  handover: CustodyReversalConflict["handover"];
  collector: { id: number; user?: { name?: string; email?: string } | null } | null;
  cashbox: { id: number; balance: string | number; currency: string } | null;
  amount: string | number | null;
  currency: string | null;
  reason: string;
  zoho_state: { status?: string; details?: Record<string, unknown> } | null;
  original_handover_amount?: string | number | null;
};

export async function listCustodyReversals(params: { status?: string; page?: number } = {}) {
  return apiFetch<CustodyReversalConflict[]>(
    `/custody-reversals${toQuery({ status: params.status, page: params.page, per_page: 20 })}`,
  );
}

export async function getCustodyReversal(id: number) {
  return apiFetch<CustodyReversalDetail>(`/custody-reversals/${id}`);
}

export async function approveCustodyReversal(id: number) {
  return apiFetch<CustodyReversalDetail>(`/custody-reversals/${id}/approve`, {
    method: "POST",
    body: JSON.stringify({}),
  });
}

export async function rejectCustodyReversal(id: number, reason: string) {
  return apiFetch<CustodyReversalConflict>(`/custody-reversals/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}
