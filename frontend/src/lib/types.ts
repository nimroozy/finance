export type UserStatus = "active" | "disabled";

export interface BranchSummary {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  username: string | null;
  phone?: string | null;
  locale: string;
  status: UserStatus;
  force_password_change: boolean;
  last_login_at?: string | null;
  roles: string[];
  permissions: string[];
  branches: BranchSummary[];
}

export interface Branch {
  id: number;
  code: string;
  name_en: string;
  name_fa: string;
  province_en: string;
  province_fa: string;
  phone: string | null;
  address: string | null;
  receipt_prefix: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface Role {
  id: number;
  name: string;
  permissions: string[];
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AuditLog {
  id: number;
  user_id: number | null;
  branch_id: number | null;
  action: string;
  entity_type: string | null;
  entity_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string } | null;
  branch?: { id: number; code: string; name_en: string } | null;
}

export interface SystemSettings {
  company_name: string | null;
  currency: string | null;
  timezone: string | null;
  setup_completed?: boolean | string | null;
}

export interface ApiSuccessResponse<T> {
  success: true;
  data: T;
  meta?: PaginationMeta;
  message?: string;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
  force_password_change: boolean;
}

export interface CreateUserPayload {
  name: string;
  email: string;
  username?: string;
  password: string;
  locale?: "en" | "fa";
  roles?: string[];
  branch_ids?: number[];
  force_password_change?: boolean;
}

export interface BranchPayload {
  code: string;
  name_en: string;
  name_fa: string;
  province_en: string;
  province_fa: string;
  phone?: string;
  address?: string;
  receipt_prefix: string;
  is_active?: boolean;
}

export interface SettingsPayload {
  company_name?: string;
  currency?: string;
  timezone?: string;
}

export type ZohoConnectionStatus = "connected" | "disconnected" | "error";

export interface ZohoStatus {
  connected: boolean;
  status: ZohoConnectionStatus | string;
  organization_id: string | null;
  organization_name: string | null;
  data_center: string | null;
  accounts_domain: string | null;
  api_domain: string | null;
  token_expires_at: string | null;
  last_connected_at: string | null;
  last_error: string | null;
  scopes: string[] | string | null;
  has_access_token: boolean;
  has_refresh_token: boolean;
  last_customer_sync_at: string | null;
  last_invoice_sync_at: string | null;
}

export interface ZohoDataCenter {
  code: string;
  label: string;
  accounts: string;
  api: string;
}

export interface ZohoOrganization {
  id: number;
  zoho_connection_id: number;
  zoho_org_id: string;
  name: string;
  currency_code: string | null;
  is_selected: boolean;
}

export type ZohoSyncType = "customers" | "invoices" | "full";

export type ZohoSyncJobStatus =
  | "pending"
  | "running"
  | "completed"
  | "partially_completed"
  | "failed"
  | "retrying"
  | "cancelled";

export interface ZohoSyncJob {
  id: number;
  type: string;
  status: ZohoSyncJobStatus | string;
  started_at: string | null;
  finished_at: string | null;
  triggered_by: number | null;
  progress: Record<string, unknown> | null;
  stats: Record<string, unknown> | null;
  error_message: string | null;
  parent_job_id: number | null;
  created_at?: string;
  updated_at?: string;
}

export interface ZohoApiLog {
  id: number;
  zoho_sync_job_id: number | null;
  request_type: string | null;
  endpoint_name: string | null;
  method: string | null;
  entity_type: string | null;
  local_entity_id: number | null;
  zoho_entity_id: string | null;
  http_status: number | null;
  zoho_code: string | null;
  attempt: number | null;
  duration_ms: number | null;
  success: boolean;
  error_message: string | null;
  created_at: string | null;
}

export type ZohoMappingMethod =
  | "zoho_branch"
  | "zoho_location"
  | "reporting_tag"
  | "custom_field";

export interface ZohoBranchMapping {
  id: number;
  branch_id: number;
  mapping_method: ZohoMappingMethod | string;
  zoho_value: string;
  zoho_label: string | null;
  is_active: boolean;
  branch?: BranchSummary | null;
}

export interface ZohoBranchMappingPayload {
  branch_id: number;
  mapping_method: ZohoMappingMethod | string;
  zoho_value: string;
  zoho_label?: string | null;
  is_active?: boolean;
}

export interface ZohoReportingTagMapping {
  id: number;
  tag_id: string;
  tag_option_id: string;
  tag_name: string;
  option_name: string;
  branch_id: number | null;
  branch?: BranchSummary | null;
}

export interface Customer {
  id: number;
  branch_id: number | null;
  zoho_contact_id: string | null;
  customer_number: string | null;
  contact_name: string | null;
  company_name: string | null;
  phone: string | null;
  mobile: string | null;
  whatsapp_number: string | null;
  email: string | null;
  billing_address: string | null;
  shipping_address: string | null;
  currency: string | null;
  outstanding_receivable: string | number | null;
  payment_terms: string | null;
  status: string;
  reporting_tags?: unknown;
  zoho_created_at?: string | null;
  zoho_modified_at?: string | null;
  last_synced_at?: string | null;
  sync_status?: string | null;
  is_unmapped: boolean;
  branch?: BranchSummary | null;
  custom_fields?: CustomerCustomField[];
}

export interface CustomerCustomField {
  id: number;
  customer_id: number;
  field_name: string;
  field_value: string | null;
}

export interface Invoice {
  id: number;
  branch_id: number | null;
  customer_id: number | null;
  zoho_invoice_id: string | null;
  invoice_number: string | null;
  invoice_date: string | null;
  due_date: string | null;
  status: string;
  currency: string | null;
  total: string | number | null;
  amount_paid: string | number | null;
  credits_applied: string | number | null;
  balance: string | number | null;
  last_synced_at?: string | null;
  sync_status?: string | null;
  customer?: Pick<Customer, "id" | "contact_name" | "customer_number"> | null;
  branch?: BranchSummary | null;
  custom_fields?: { id: number; field_name: string; field_value: string | null }[];
}

export interface CustomerListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  branch_id?: number | string;
  exclude_unmapped?: boolean;
}

export interface InvoiceListParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  customer_id?: number | string;
  branch_id?: number | string;
}

export interface DebtorListParams {
  page?: number;
  per_page?: number;
  search?: string;
  branch_id?: number | string;
  min_balance?: number | string;
  max_balance?: number | string;
  days_overdue_min?: number | string;
  status?: string;
  sort?: string;
}
