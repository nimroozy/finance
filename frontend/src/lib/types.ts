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
  latitude?: string | number | null;
  longitude?: string | number | null;
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

export type AssignmentStatus =
  | "assigned"
  | "accepted"
  | "in_progress"
  | "closed"
  | "cancelled"
  | "reassigned"
  | "fully_resolved";

export type AssignmentPriority = "low" | "normal" | "high" | "urgent";

export interface CollectorUserSummary {
  id: number;
  name: string;
  email?: string;
  status?: UserStatus;
}

export interface Collector {
  id: number;
  user_id: number;
  employee_code: string | null;
  max_active_assignments: number | null;
  is_active: boolean;
  notes: string | null;
  user?: CollectorUserSummary | null;
  active_assignments_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface CustomerAssignment {
  id: number;
  branch_id: number | null;
  customer_id: number;
  collector_id: number;
  assigned_by?: number | null;
  status: AssignmentStatus | string;
  is_active: boolean;
  is_primary?: boolean;
  priority: AssignmentPriority | string;
  assignment_source?: string | null;
  debt_snapshot_outstanding?: string | number | null;
  debt_snapshot_overdue?: string | number | null;
  debt_snapshot_invoice_count?: number | null;
  debt_snapshot_currency?: string | null;
  due_date: string | null;
  notes: string | null;
  cancel_reason?: string | null;
  accepted_at?: string | null;
  first_viewed_at?: string | null;
  closed_at?: string | null;
  reassigned_from_id?: number | null;
  bulk_operation_id?: number | null;
  created_at?: string;
  updated_at?: string;
  customer?: Pick<
    Customer,
    | "id"
    | "contact_name"
    | "company_name"
    | "customer_number"
    | "phone"
    | "mobile"
    | "whatsapp_number"
    | "outstanding_receivable"
    | "currency"
    | "latitude"
    | "longitude"
    | "billing_address"
    | "shipping_address"
    | "email"
  > | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  branch?: BranchSummary | null;
  history?: AssignmentHistoryEntry[];
  comments?: AssignmentComment[];
}

export interface AssignmentHistoryEntry {
  id: number;
  assignment_id: number;
  event: string;
  from_status: string | null;
  to_status: string | null;
  changed_by: number | null;
  from_collector_id?: number | null;
  to_collector_id?: number | null;
  notes?: string | null;
  metadata?: Record<string, unknown> | null;
  created_at: string;
  changedBy?: { id: number; name: string } | null;
}

export interface AssignmentComment {
  id: number;
  assignment_id: number;
  body: string;
  created_at?: string;
}

export interface AssignmentListParams {
  page?: number;
  per_page?: number;
  status?: string;
  collector_id?: number | string;
  branch_id?: number | string;
  is_active?: boolean | string;
  customer_id?: number | string;
}

export interface CreateAssignmentPayload {
  customer_id: number;
  collector_id: number;
  priority?: AssignmentPriority | string;
  due_date?: string;
  notes?: string;
}

export interface BulkAssignPayload {
  customer_ids: number[];
  collector_id: number;
  branch_id?: number;
  priority?: AssignmentPriority | string;
  due_date?: string;
  notes?: string;
}

export interface ReassignPayload {
  collector_id: number;
  priority?: AssignmentPriority | string;
  due_date?: string;
  notes?: string;
}

export interface AutoAssignPreviewPayload {
  branch_id: number;
  strategy?: "round_robin" | "least_loaded";
  customer_ids?: number[];
  collector_ids?: number[];
}

export interface AssignmentBulkOperation {
  id: number;
  branch_id: number;
  created_by?: number;
  strategy: string;
  status: string;
  preview_payload?: Record<string, unknown> | unknown[] | null;
  result_payload?: Record<string, unknown> | unknown[] | null;
  selected_count?: number;
  total_outstanding?: string | number | null;
  confirmed_at?: string | null;
}

export interface CollectorWorkload {
  collector_id: number;
  user_id: number;
  name: string | null;
  employee_code: string | null;
  active_assignments: number;
  max_active_assignments: number | null;
}

export interface VisitOutcome {
  code: string;
  label_en: string;
  label_fa: string;
}

export interface CollectionVisit {
  id: number;
  branch_id: number | null;
  customer_id: number;
  assignment_id: number | null;
  collector_id: number | null;
  route_stop_id?: number | null;
  recorded_by?: number | null;
  visited_at: string | null;
  outcome: string;
  notes: string | null;
  follow_up_required: boolean;
  follow_up_date: string | null;
  latitude: string | number | null;
  longitude: string | number | null;
  customer_latitude?: string | number | null;
  customer_longitude?: string | number | null;
  distance_meters?: string | number | null;
  gps_risk_level?: string | null;
  correction_note?: string | null;
  correction_by?: number | null;
  correction_at?: string | null;
  created_at?: string;
  updated_at?: string;
  customer?: Pick<
    Customer,
    | "id"
    | "contact_name"
    | "company_name"
    | "phone"
    | "mobile"
    | "latitude"
    | "longitude"
    | "billing_address"
  > | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  files?: VisitFile[];
  promise?: PromiseToPay | null;
}

export interface VisitFile {
  id: number;
  visit_id: number;
  uploaded_by?: number | null;
  original_name: string;
  mime_type: string | null;
  size: number | null;
  created_at?: string;
}

export interface VisitListParams {
  page?: number;
  per_page?: number;
  customer_id?: number | string;
  collector_id?: number | string;
  outcome?: string;
  branch_id?: number | string;
}

export interface CreateVisitPayload {
  assignment_id?: number;
  customer_id?: number;
  collector_id?: number;
  route_stop_id?: number;
  visited_at?: string;
  outcome: string;
  notes?: string;
  follow_up_required?: boolean;
  follow_up_date?: string;
  latitude?: number;
  longitude?: number;
  promise?: {
    amount: number | string;
    promised_date: string;
    currency?: string;
    notes?: string;
    allow_past_date?: boolean;
  };
}

export type RouteStatus =
  | "draft"
  | "published"
  | "in_progress"
  | "completed"
  | "cancelled";

export interface CollectionRouteStop {
  id: number;
  route_id: number;
  customer_id: number;
  assignment_id: number | null;
  sequence: number;
  status: string;
  visit_id: number | null;
  notes: string | null;
  completed_at: string | null;
  customer?: Pick<
    Customer,
    | "id"
    | "contact_name"
    | "company_name"
    | "phone"
    | "mobile"
    | "latitude"
    | "longitude"
    | "billing_address"
    | "shipping_address"
  > | null;
}

export interface CollectionRoute {
  id: number;
  branch_id: number;
  collector_id: number;
  created_by?: number | null;
  name: string;
  route_date: string;
  status: RouteStatus | string;
  notes: string | null;
  published_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  cancel_reason?: string | null;
  created_at?: string;
  updated_at?: string;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  stops?: CollectionRouteStop[];
  branch?: BranchSummary | null;
}

export interface RouteListParams {
  page?: number;
  per_page?: number;
  status?: string;
  collector_id?: number | string;
  branch_id?: number | string;
}

export interface CreateRoutePayload {
  branch_id: number;
  collector_id: number;
  name: string;
  route_date: string;
  notes?: string;
  stops?: {
    customer_id: number;
    assignment_id?: number;
    sequence?: number;
    notes?: string;
  }[];
}

export type PromiseStatus =
  | "active"
  | "due_soon"
  | "due_today"
  | "overdue"
  | "fulfilled"
  | "cancelled"
  | "superseded";

export interface PromiseToPay {
  id: number;
  branch_id: number | null;
  customer_id: number;
  assignment_id: number | null;
  visit_id: number | null;
  collector_id: number | null;
  created_by?: number | null;
  amount: string | number;
  currency: string | null;
  promised_date: string;
  status: PromiseStatus | string;
  notes: string | null;
  fulfilled_at?: string | null;
  cancelled_at?: string | null;
  cancel_reason?: string | null;
  superseded_by_id?: number | null;
  created_at?: string;
  updated_at?: string;
  customer?: Pick<Customer, "id" | "contact_name" | "company_name"> | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
}

export interface PromiseListParams {
  page?: number;
  per_page?: number;
  status?: string;
  customer_id?: number | string;
  branch_id?: number | string;
}

export interface CreatePromisePayload {
  customer_id: number;
  assignment_id?: number;
  visit_id?: number;
  collector_id?: number;
  amount: number | string;
  currency?: string;
  promised_date: string;
  notes?: string;
  allow_past_date?: boolean;
}

export interface CustomerNote {
  id: number;
  branch_id: number | null;
  customer_id: number;
  author_id: number | null;
  assignment_id: number | null;
  body: string;
  edit_history?: unknown;
  created_at?: string;
  updated_at?: string;
  author?: { id: number; name: string } | null;
}

export interface AppNotification {
  id: number;
  user_id: number;
  branch_id: number | null;
  type: string;
  title: string;
  body: string | null;
  data?: Record<string, unknown> | null;
  read_at: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface CollectorDashboard {
  collector_id: number;
  active_assignments: number;
  visits_today: number;
  open_promises: number;
  routes_today: CollectionRoute[];
}

export interface CreateCollectorPayload {
  user_id: number;
  employee_code?: string;
  max_active_assignments?: number;
  notes?: string;
}

export interface UpdateCollectorPayload {
  employee_code?: string | null;
  max_active_assignments?: number | null;
  is_active?: boolean;
  notes?: string | null;
}
