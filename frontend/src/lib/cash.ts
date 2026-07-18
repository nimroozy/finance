import { apiFetch, toQuery } from "@/lib/api";
import type {
  CashboxTransfer,
  CashboxTransferPayload,
  CashReconciliationRun,
  CashTransferType,
} from "@/lib/types";

export async function listCashboxTransfers(page = 1, type?: CashTransferType) {
  const response = await apiFetch<CashboxTransfer[] | { data: CashboxTransfer[] }>(
    `/cashbox-transfers${toQuery({ page, per_page: 20, type })}`,
  );
  const nested = response.data as { data?: CashboxTransfer[]; current_page?: number; last_page?: number } | CashboxTransfer[];
  if (Array.isArray(nested)) {
    return response as Awaited<ReturnType<typeof apiFetch<CashboxTransfer[]>>>;
  }
  return {
    ...response,
    data: Array.isArray(nested?.data) ? nested.data : [],
    meta: response.meta ?? {
      current_page: nested?.current_page ?? page,
      last_page: nested?.last_page ?? 1,
      per_page: 20,
      total: Array.isArray(nested?.data) ? nested.data.length : 0,
    },
  };
}

export async function createCashboxTransfer(payload: CashboxTransferPayload) {
  return apiFetch<CashboxTransfer>("/cashbox-transfers/draft", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function transitionCashboxTransfer(
  id: number,
  action: "submit" | "approve" | "send" | "receive",
) {
  return apiFetch<CashboxTransfer>(`/cashbox-transfers/${id}/${action}`, {
    method: "POST",
    body: "{}",
  });
}

export async function reverseCashboxTransfer(id: number, reason: string) {
  return apiFetch<CashboxTransfer>(`/cashbox-transfers/${id}/reverse`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function listCashReconciliations(page = 1) {
  const response = await apiFetch<CashReconciliationRun[] | { data: CashReconciliationRun[] }>(
    `/cash-reconciliations${toQuery({ page, per_page: 20 })}`,
  );
  const nested = response.data as
    | { data?: CashReconciliationRun[]; current_page?: number; last_page?: number }
    | CashReconciliationRun[];
  if (Array.isArray(nested)) {
    return response as Awaited<ReturnType<typeof apiFetch<CashReconciliationRun[]>>>;
  }
  return {
    ...response,
    data: Array.isArray(nested?.data) ? nested.data : [],
    meta: response.meta ?? {
      current_page: nested?.current_page ?? page,
      last_page: nested?.last_page ?? 1,
      per_page: 20,
      total: Array.isArray(nested?.data) ? nested.data.length : 0,
    },
  };
}

export async function runCashReconciliation(payload: {
  cashbox_id: number;
  date: string;
  counted_amount?: string;
}) {
  return apiFetch<CashReconciliationRun>("/cash-reconciliations/run", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}
