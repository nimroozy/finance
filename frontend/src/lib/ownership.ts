import { apiFetch, apiFetchList, toQuery } from "@/lib/api";
import type { Branch, Collector, Customer } from "@/lib/types";

export type CustomerOwnership = {
  id: number;
  customer_id: number;
  branch_id: number;
  collector_id: number;
  assigned_by?: number | null;
  start_date: string;
  end_date?: string | null;
  status: string;
  reason?: string | null;
  area?: string | null;
  route_label?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  customer?: Customer | null;
  collector?: Collector | null;
  branch?: Branch | null;
};

export type CustomerWorkQueueEntry = {
  id: number;
  customer_id: number;
  branch_id: number;
  effective_collector_id: number | null;
  ownership_source: string;
  total_open_balance: string | number | null;
  open_invoice_count: number;
  oldest_due_date?: string | null;
  days_overdue?: number | null;
  last_payment_date?: string | null;
  last_visit_at?: string | null;
  promise_status?: string | null;
  priority?: string | null;
  work_status?: string | null;
  last_routed_at?: string | null;
  customer?: Customer | null;
};

export type OwnershipHistoryEntry = {
  id: number;
  ownership_id: number;
  customer_id: number;
  branch_id: number;
  collector_id: number;
  actor_id?: number | null;
  event: string;
  before?: Record<string, unknown> | null;
  after?: Record<string, unknown> | null;
  notes?: string | null;
  created_at?: string;
};

export type TemporaryAssignment = {
  id: number;
  customer_id: number;
  branch_id: number;
  temporary_collector_id: number;
  permanent_collector_id?: number | null;
  start_date: string;
  end_date: string;
  reason?: string | null;
  priority?: string | null;
  status: string;
  created_by?: number | null;
  approved_by?: number | null;
  is_active: boolean;
  expired_at?: string | null;
  cancelled_at?: string | null;
  customer?: Customer | null;
  temporaryCollector?: Collector | null;
};

export type OwnershipConflict = {
  id: number;
  customer_id: number;
  branch_id: number;
  conflict_type: string;
  status: string;
  details?: Record<string, unknown> | null;
  resolved_by?: number | null;
  resolved_at?: string | null;
  created_at?: string;
  customer?: Customer | null;
};

export type BranchReceivableRow = {
  branch: { id: number; code: string; name_en: string; name_fa?: string | null };
  total_receivable: string;
  active_debtor_customers: number;
  open_invoice_count: number;
  assigned_customers: number;
  unassigned_customers: number;
  permanently_owned_customers: number;
  temporarily_assigned_customers: number;
  amount_by_collector: Array<{ effective_collector_id: number | null; amount: string; customers: number }>;
  collected_today: string;
  collected_this_month: string;
  cash_held_by_collectors: string;
  pending_handovers: number;
  overdue_promises: number;
  sync_freshness: string | null;
};

export type BranchPaymentMapping = {
  id: number;
  branch_id: number;
  zoho_location_id?: string | null;
  zoho_account_id?: string | null;
  zoho_account_name?: string | null;
  zoho_payment_mode_id?: string | null;
  zoho_payment_mode_name?: string | null;
  currency?: string | null;
  receipt_prefix?: string | null;
  is_active: boolean;
  readiness_status?: string | null;
  validation_status?: string | null;
  last_validated_at?: string | null;
  validation_message?: string | null;
  mapping_version?: number | null;
  updated_by?: number | null;
  branch?: Branch | null;
};

export type ZohoAccountOption = {
  id: number;
  zoho_account_id: string;
  name: string;
  account_type?: string | null;
  account_code?: string | null;
  is_active: boolean;
};

export type ZohoPaymentModeOption = {
  id: number;
  zoho_payment_mode_id: string;
  name: string;
  is_active: boolean;
};

export type BranchPaymentReadiness = {
  status: string;
  ready: boolean;
  message?: string | null;
  mapping: BranchPaymentMapping | null;
};

export function listOwnership(params?: { page?: number; status?: string; branch_id?: number }) {
  return apiFetchList<CustomerOwnership>(`/customer-ownership${toQuery(params ?? {})}`);
}

export function listUnassignedOwnership(params?: { page?: number }) {
  return apiFetchList<CustomerWorkQueueEntry>(`/customer-ownership/unassigned${toQuery(params ?? {})}`);
}

export function assignOwnership(body: {
  customer_id: number;
  collector_id: number;
  start_date: string;
  reason?: string;
  area?: string;
  route_label?: string;
}) {
  return apiFetch<CustomerOwnership>("/customer-ownership", { method: "POST", body: JSON.stringify(body) });
}

export function bulkAssignOwnership(body: {
  customer_ids: number[];
  collector_id: number;
  start_date: string;
  reason?: string;
}) {
  return apiFetch<{ assigned: number; skipped: number }>("/customer-ownership/bulk", {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function transferOwnership(body: {
  customer_id: number;
  to_collector_id: number;
  effective_date: string;
  reason: string;
}) {
  return apiFetch<CustomerOwnership>("/customer-ownership/transfer", { method: "POST", body: JSON.stringify(body) });
}

export function endOwnership(id: number, reason: string) {
  return apiFetch<CustomerOwnership>(`/customer-ownership/${id}/end`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export function ownershipHistory(customerId: number) {
  return apiFetch<OwnershipHistoryEntry[]>(`/customer-ownership/history/${customerId}`);
}

export function listTemporaryAssignments(params?: { page?: number }) {
  return apiFetchList<TemporaryAssignment>(`/temporary-assignments${toQuery(params ?? {})}`);
}

export function createTemporaryAssignment(body: {
  customer_id: number;
  temporary_collector_id: number;
  start_date: string;
  end_date: string;
  reason?: string;
}) {
  return apiFetch<TemporaryAssignment>("/temporary-assignments", { method: "POST", body: JSON.stringify(body) });
}

export function cancelTemporaryAssignment(id: number, reason?: string) {
  return apiFetch<TemporaryAssignment>(`/temporary-assignments/${id}/cancel`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export function listOwnershipConflicts(params?: { page?: number }) {
  return apiFetchList<OwnershipConflict>(`/ownership-conflicts${toQuery(params ?? {})}`);
}

export function branchReceivables() {
  return apiFetch<BranchReceivableRow[]>("/reports/branch-receivables");
}

export function listBranchPaymentMappings() {
  return apiFetch<BranchPaymentMapping[]>("/branch-payment-mappings");
}

export function listZohoAccounts(type?: string) {
  return apiFetch<ZohoAccountOption[]>(`/branch-payment-mappings/accounts${toQuery({ type })}`);
}

export function listZohoPaymentModes() {
  return apiFetch<ZohoPaymentModeOption[]>("/branch-payment-mappings/payment-modes");
}

export function upsertBranchPaymentMapping(body: Record<string, unknown>) {
  return apiFetch<BranchPaymentMapping>("/branch-payment-mappings", { method: "POST", body: JSON.stringify(body) });
}

export function validateBranchPaymentMapping(branchId: number) {
  return apiFetch<BranchPaymentMapping>(`/branch-payment-mappings/${branchId}/validate`, {
    method: "POST",
    body: "{}",
  });
}

export function branchPaymentReadiness(branchId: number) {
  return apiFetch<BranchPaymentReadiness>(`/branch-payment-mappings/${branchId}/readiness`);
}

export function collectorPermanentCustomers(params?: { page?: number }) {
  return apiFetchList<CustomerOwnership>(`/collector/permanent-customers${toQuery(params ?? {})}`);
}

export function collectorDebtors(params?: { page?: number }) {
  return apiFetchList<CustomerWorkQueueEntry>(`/collector/debtors${toQuery(params ?? {})}`);
}
