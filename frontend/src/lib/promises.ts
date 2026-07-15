import { apiFetch, toQuery } from "@/lib/api";
import type {
  CreatePromisePayload,
  PromiseListParams,
  PromiseToPay,
} from "@/lib/types";

export async function listPromises(params: PromiseListParams = {}) {
  return apiFetch<PromiseToPay[]>(
    `/promises${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      status: params.status,
      customer_id: params.customer_id,
      branch_id: params.branch_id,
    })}`,
  );
}

export async function createPromise(payload: CreatePromisePayload) {
  return apiFetch<PromiseToPay>("/promises", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function cancelPromise(id: number, reason?: string) {
  return apiFetch<PromiseToPay>(`/promises/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function fulfillPromise(id: number) {
  return apiFetch<PromiseToPay>(`/promises/${id}/fulfill`, { method: "POST" });
}

export async function supersedePromise(
  id: number,
  payload: {
    amount: number | string;
    promised_date: string;
    currency?: string;
    notes?: string;
    allow_past_date?: boolean;
  },
) {
  return apiFetch<PromiseToPay>(`/promises/${id}/supersede`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}
