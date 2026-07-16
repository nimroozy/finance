import { apiFetch } from "@/lib/api";

export function listOwnership(params?: Record<string, string | number>) {
  const q = new URLSearchParams(params as Record<string, string>).toString();
  return apiFetch(`/customer-ownership${q ? `?${q}` : ""}`);
}
export function listUnassignedOwnership() {
  return apiFetch("/customer-ownership/unassigned");
}
export function assignOwnership(body: Record<string, unknown>) {
  return apiFetch("/customer-ownership", { method: "POST", body: JSON.stringify(body) });
}
export function bulkAssignOwnership(body: Record<string, unknown>) {
  return apiFetch("/customer-ownership/bulk", { method: "POST", body: JSON.stringify(body) });
}
export function transferOwnership(body: Record<string, unknown>) {
  return apiFetch("/customer-ownership/transfer", { method: "POST", body: JSON.stringify(body) });
}
export function endOwnership(id: number, reason: string) {
  return apiFetch(`/customer-ownership/${id}/end`, { method: "POST", body: JSON.stringify({ reason }) });
}
export function ownershipHistory(customerId: number) {
  return apiFetch(`/customer-ownership/history/${customerId}`);
}
export function listTemporaryAssignments() {
  return apiFetch("/temporary-assignments");
}
export function createTemporaryAssignment(body: Record<string, unknown>) {
  return apiFetch("/temporary-assignments", { method: "POST", body: JSON.stringify(body) });
}
export function cancelTemporaryAssignment(id: number, reason?: string) {
  return apiFetch(`/temporary-assignments/${id}/cancel`, { method: "POST", body: JSON.stringify({ reason }) });
}
export function listOwnershipConflicts() {
  return apiFetch("/ownership-conflicts");
}
export function branchReceivables() {
  return apiFetch("/reports/branch-receivables");
}
export function listBranchPaymentMappings() {
  return apiFetch("/branch-payment-mappings");
}
export function listZohoAccounts(type?: string) {
  const q = type ? `?type=${encodeURIComponent(type)}` : "";
  return apiFetch(`/branch-payment-mappings/accounts${q}`);
}
export function listZohoPaymentModes() {
  return apiFetch("/branch-payment-mappings/payment-modes");
}
export function upsertBranchPaymentMapping(body: Record<string, unknown>) {
  return apiFetch("/branch-payment-mappings", { method: "POST", body: JSON.stringify(body) });
}
export function validateBranchPaymentMapping(branchId: number) {
  return apiFetch(`/branch-payment-mappings/${branchId}/validate`, { method: "POST", body: "{}" });
}
export function branchPaymentReadiness(branchId: number) {
  return apiFetch(`/branch-payment-mappings/${branchId}/readiness`);
}
export function collectorPermanentCustomers() {
  return apiFetch("/collector/permanent-customers");
}
export function collectorDebtors() {
  return apiFetch("/collector/debtors");
}
