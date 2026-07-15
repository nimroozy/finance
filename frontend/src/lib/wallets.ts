import { apiFetch, toQuery } from "@/lib/api";
import type {
  CollectorWallet,
  CollectorWalletTransaction,
} from "@/lib/types";

export async function getCollectorWallet(params: {
  collector_id?: number | string;
  branch_id?: number | string;
  currency?: string;
} = {}) {
  return apiFetch<CollectorWallet>(
    `/collector/wallet${toQuery({
      collector_id: params.collector_id,
      branch_id: params.branch_id,
      currency: params.currency,
    })}`,
  );
}

export async function listWalletTransactions(params: {
  page?: number;
  per_page?: number;
  collector_id?: number | string;
  branch_id?: number | string;
} = {}) {
  return apiFetch<CollectorWalletTransaction[]>(
    `/collector/wallet/transactions${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 20,
      collector_id: params.collector_id,
      branch_id: params.branch_id,
    })}`,
  );
}
