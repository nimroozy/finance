import { apiFetch, toQuery } from "@/lib/api";
import type {
  CashboxTransfer,
  CashboxTransferPayload,
  CashReconciliationRun,
  CashTransferType,
} from "@/lib/types";

export async function listCashboxTransfers(page = 1, type?: CashTransferType) {
  return apiFetch<CashboxTransfer[]>(
    `/cashbox-transfers${toQuery({ page, per_page: 20, type })}`,
  );
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
  return apiFetch<CashReconciliationRun[]>(
    `/cash-reconciliations${toQuery({ page, per_page: 20 })}`,
  );
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
