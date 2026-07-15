import { apiFetch, toQuery } from "@/lib/api";
import type {
  CreatePaymentPayload,
  Payment,
  PaymentListParams,
  PaymentMethod,
  PaymentPreview,
  PaymentReversal,
  PaymentSyncStatus,
  PaymentsSummary,
} from "@/lib/types";

export function newIdempotencyKey() {
  return crypto.randomUUID();
}

export async function listPaymentMethods() {
  return apiFetch<PaymentMethod[]>("/payment-methods");
}

export async function previewPayment(payload: CreatePaymentPayload) {
  return apiFetch<PaymentPreview>("/payments/preview", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createPaymentDraft(payload: CreatePaymentPayload) {
  const key = payload.idempotency_key || newIdempotencyKey();
  return apiFetch<Payment>("/payments/draft", {
    method: "POST",
    headers: { "Idempotency-Key": key },
    body: JSON.stringify({ ...payload, idempotency_key: key }),
  });
}

export async function confirmPayment(
  uuid: string,
  idempotencyKey?: string,
) {
  const key = idempotencyKey || newIdempotencyKey();
  return apiFetch<Payment>(`/payments/${uuid}/confirm`, {
    method: "POST",
    headers: { "Idempotency-Key": key },
    body: JSON.stringify({ idempotency_key: key }),
  });
}

export async function listPayments(params: PaymentListParams = {}) {
  return apiFetch<Payment[]>(
    `/payments${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
      branch_id: params.branch_id,
      collector_id: params.collector_id,
      customer_id: params.customer_id,
      zoho_sync_status: params.zoho_sync_status,
      search: params.search,
    })}`,
  );
}

export async function getPayment(uuid: string) {
  return apiFetch<Payment>(`/payments/${uuid}`);
}

export async function getPaymentSyncStatus(uuid: string) {
  return apiFetch<PaymentSyncStatus>(`/payments/${uuid}/sync-status`);
}

export async function retryPaymentSync(uuid: string) {
  return apiFetch<Payment>(`/payments/${uuid}/retry-sync`, {
    method: "POST",
  });
}

export async function requestPaymentReversal(uuid: string, reason: string) {
  return apiFetch<PaymentReversal>(`/payments/${uuid}/reversal-request`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function approveReversal(id: number) {
  return apiFetch<PaymentReversal>(`/reversals/${id}/approve`, {
    method: "POST",
  });
}

export async function rejectReversal(id: number, reason: string) {
  return apiFetch<PaymentReversal>(`/reversals/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function reportPaymentsSummary(params: {
  branch_id?: number | string;
  from?: string;
  to?: string;
} = {}) {
  return apiFetch<PaymentsSummary>(
    `/reports/payments-summary${toQuery(params)}`,
  );
}

export async function reportPaymentsSyncFailures(branchId?: number | string) {
  return apiFetch<Payment[]>(
    `/reports/payments-sync-failures${toQuery({ branch_id: branchId })}`,
  );
}

export function paymentLatestReversal(payment: Payment) {
  return payment.latest_reversal ?? payment.latestReversal ?? null;
}
