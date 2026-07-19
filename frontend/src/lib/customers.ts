import { apiDownload, apiFetch, toQuery } from "@/lib/api";
import type {
  Customer,
  CustomerListParams,
  DebtorListParams,
  Invoice,
  InvoiceListParams,
} from "@/lib/types";

export async function listCustomers(params: CustomerListParams = {}) {
  return apiFetch<Customer[]>(
    `/customers${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      search: params.search,
      status: params.status,
      branch_id: params.branch_id,
      sync_status: params.sync_status,
      exclude_unmapped: params.exclude_unmapped,
    })}`,
  );
}

export async function getCustomer(id: number) {
  return apiFetch<Customer>(`/customers/${id}`);
}

export async function listUnmappedCustomers(page = 1, perPage = 15) {
  return apiFetch<Customer[]>(
    `/customers/unmapped${toQuery({ page, per_page: perPage })}`,
  );
}

export async function mapCustomerBranch(id: number, branchId: number) {
  return apiFetch<Customer>(`/customers/${id}/map-branch`, {
    method: "POST",
    body: JSON.stringify({ branch_id: branchId }),
  });
}

export async function listInvoices(params: InvoiceListParams = {}) {
  return apiFetch<Invoice[]>(
    `/invoices${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      search: params.search,
      status: params.status,
      customer_id: params.customer_id,
      branch_id: params.branch_id,
    })}`,
  );
}

export async function getInvoice(id: number) {
  return apiFetch<Invoice>(`/invoices/${id}`);
}

export async function listDebtors(params: DebtorListParams = {}) {
  return apiFetch<Customer[]>(
    `/debtors${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      search: params.search,
      branch_id: params.branch_id,
      min_balance: params.min_balance,
      max_balance: params.max_balance,
      days_overdue_min: params.days_overdue_min,
      status: params.status,
      sort: params.sort,
    })}`,
  );
}

export async function exportDebtors(params: DebtorListParams = {}) {
  const path = `/debtors/export${toQuery({
    search: params.search,
    branch_id: params.branch_id,
    min_balance: params.min_balance,
    max_balance: params.max_balance,
    days_overdue_min: params.days_overdue_min,
    status: params.status,
    sort: params.sort,
  })}`;
  await apiDownload(path, "debtors.csv");
}
