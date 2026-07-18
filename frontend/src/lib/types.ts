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
  default_app_id?: string | null;
  ui_preferences?: {
    locale?: string | null;
    theme?: "light" | "dark" | "system" | null;
    default_app_id?: string | null;
    favorite_app_ids?: string[];
    recent_app_ids?: string[];
    date_format?: string | null;
    calendar_system?: string | null;
  } | null;
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
  zoho_location_id?: string | null;
  mapping_type?: string | null;
  zoho_sync_status?: string | null;
  last_structure_sync_at?: string | null;
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
  mapped_customer_count?: number;
  mapped_invoice_count?: number;
  branch?: Branch | null;
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

export interface ZohoLocation {
  id: number;
  zoho_location_id: string;
  organization_id: string | null;
  name: string;
  code: string | null;
  is_active: boolean;
  is_primary: boolean;
  sync_status?: string | null;
  last_synced_at?: string | null;
}

export interface ZohoReportingTagOption {
  id: number;
  zoho_tag_option_id: string;
  zoho_tag_id: string;
  name: string;
  is_active: boolean;
  last_synced_at?: string | null;
}

export interface ZohoReportingTag {
  id: number;
  zoho_tag_id: string;
  organization_id: string | null;
  name: string;
  associated_with: string | null;
  is_active: boolean;
  sync_status?: string | null;
  last_synced_at?: string | null;
  options: ZohoReportingTagOption[];
}

export interface ZohoPaymentMode {
  id: number;
  zoho_payment_mode_id: string;
  organization_id: string | null;
  name: string;
  is_active: boolean;
  last_synced_at?: string | null;
}

export interface ZohoSyncCursor {
  id: number;
  entity: string;
  successful_cursor: string | null;
  last_requested_from?: string | null;
  last_requested_to?: string | null;
  last_success_at: string | null;
  last_error: string | null;
}

export interface ZohoSyncHeartbeat {
  id: number;
  component: string;
  status: string;
  last_heartbeat_at: string | null;
  last_start_at: string | null;
  last_completion_at: string | null;
  last_duration_ms?: number | null;
  next_expected_at?: string | null;
  last_error: string | null;
}

export interface ZohoCircuitBreaker {
  id: number;
  sync_type: string;
  state: string;
  failure_count: number;
  last_error_class: string | null;
  last_error_message: string | null;
  opened_at: string | null;
  resume_after: string | null;
}

export interface ZohoHealth {
  connection: (Partial<ZohoStatus> & {
    id?: number;
    organization_id?: string | null;
    organization_name?: string | null;
  }) | null;
  cursors: Record<string, ZohoSyncCursor>;
  circuit_breakers: Record<string, ZohoCircuitBreaker>;
  heartbeats: Record<string, ZohoSyncHeartbeat>;
  failed_jobs: number;
  structure: {
    locations: number;
    reporting_tags: number;
    payment_modes: number;
  };
  checked_at: string;
}

export interface ZohoAutoMatchResult {
  branch_id: number;
  zoho_location_id: string | null;
  location_name: string | null;
  matched: boolean;
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
  zoho_synced_balance?: string | number | null;
  local_pending_allocation?: string | number | null;
  effective_balance?: string | number | null;
  balance_last_synced_at?: string | null;
  awaiting_zoho_refresh?: boolean;
  balance_sync_status?: string | null;
  balance_sync_warning?: string | null;
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
  sync_status?: string;
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

export interface AssignmentsByCollectorRow {
  collector_id: number;
  status: string;
  total: number;
  total_outstanding: string | number;
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
  customer?: Pick<Customer, "id" | "contact_name" | "company_name" | "customer_number"> | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  branch?: BranchSummary | null;
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

export type PaymentStatus =
  | "draft"
  | "confirmed_local"
  | "pending_zoho_sync"
  | "settled_pending_handover"
  | "synced"
  | "sync_failed"
  | "reversed"
  | "expired";

export type ZohoPaymentSyncStatus =
  | "pending"
  | "syncing"
  | "synced"
  | "failed"
  | "dry_run"
  | "skipped";

export interface PaymentMethod {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
  is_active: boolean;
  collector_allowed?: boolean;
  requires_reference?: boolean;
  requires_evidence?: boolean;
  affects_cash_wallet?: boolean;
  receipt_enabled?: boolean;
  sort_order?: number;
}

export interface PaymentAllocation {
  id?: number;
  payment_id?: number;
  invoice_id: number;
  amount: string | number;
  currency?: string | null;
  invoice?: Pick<
    Invoice,
    "id" | "invoice_number" | "balance" | "currency" | "status" | "due_date"
  > | null;
}

export interface PaymentSyncAttempt {
  id: number;
  payment_id: number;
  attempt?: number | null;
  success?: boolean;
  error_message?: string | null;
  created_at?: string;
}

export interface PaymentStatusHistory {
  id: number;
  payment_id: number;
  from_status: string | null;
  to_status: string;
  changed_by?: number | null;
  reason?: string | null;
  created_at?: string;
}

export type PaymentReversalStatus = "pending" | "approved" | "rejected";

export interface PaymentReversal {
  id: number;
  payment_id: number;
  requested_by?: number | null;
  branch_id?: number | null;
  status: PaymentReversalStatus | string;
  reason: string;
  rejection_reason?: string | null;
  reviewed_by?: number | null;
  reviewed_at?: string | null;
  approved_at?: string | null;
  rejected_at?: string | null;
  wallet_reversed?: boolean;
  zoho_void_attempted?: boolean;
  zoho_void_error?: string | null;
  payment?: Payment | null;
}

export interface Receipt {
  id: number;
  uuid: string;
  receipt_number: string;
  payment_id: number;
  branch_id?: number | null;
  verification_token: string;
  status: string;
  reprint_count?: number;
  pdf_path?: string | null;
  html_path?: string | null;
  language?: string | null;
  template_version?: string | null;
  issued_at?: string | null;
  voided_at?: string | null;
  payment?: Payment | null;
  branch?: BranchSummary | null;
}

export interface Payment {
  id: number;
  uuid: string;
  payment_reference?: string | null;
  customer_id: number;
  collector_id?: number | null;
  created_by?: number | null;
  confirmed_by?: number | null;
  branch_id?: number | null;
  assignment_id?: number | null;
  visit_id?: number | null;
  promise_id?: number | null;
  payment_method_id: number;
  currency: string | null;
  amount: string | number;
  status: PaymentStatus | string;
  zoho_sync_status?: ZohoPaymentSyncStatus | string | null;
  zoho_payment_id?: string | null;
  zoho_reference?: string | null;
  sync_attempts?: number | null;
  last_sync_error?: string | null;
  idempotency_key?: string | null;
  external_reference?: string | null;
  notes?: string | null;
  latitude?: string | number | null;
  longitude?: string | number | null;
  gps_accuracy?: string | number | null;
  device_info?: string | null;
  confirmed_at?: string | null;
  reversed_at?: string | null;
  receipt_id?: number | null;
  draft_expires_at?: string | null;
  created_at?: string;
  updated_at?: string;
  method?: PaymentMethod | null;
  customer?: Pick<
    Customer,
    | "id"
    | "contact_name"
    | "company_name"
    | "customer_number"
    | "phone"
    | "mobile"
    | "whatsapp_number"
    | "currency"
  > | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  allocations?: PaymentAllocation[];
  receipt?: Receipt | null;
  status_history?: PaymentStatusHistory[];
  sync_attempts_list?: PaymentSyncAttempt[];
  syncAttempts?: PaymentSyncAttempt[];
  latest_reversal?: PaymentReversal | null;
  latestReversal?: PaymentReversal | null;
  branch?: BranchSummary | null;
}

export interface PaymentPreview {
  customer_id: number;
  collector_id: number;
  branch_id: number;
  assignment_id?: number | null;
  payment_method: Pick<
    PaymentMethod,
    "id" | "code" | "name_en" | "affects_cash_wallet" | "requires_reference"
  >;
  currency: string;
  amount: string;
  allocations: {
    invoice_id: number;
    invoice_number: string | null;
    amount: string;
    effective_available: string;
    effective_balance?: string;
    zoho_balance: string;
    zoho_synced_balance?: string;
    local_pending_allocation?: string;
    balance_last_synced_at?: string | null;
    awaiting_zoho_refresh?: boolean;
    balance_sync_status?: string;
    balance_sync_warning?: string | null;
  }[];
  warnings?: string[];
}

export interface PaymentSyncStatus {
  uuid: string;
  status: string;
  zoho_sync_status: string | null;
  zoho_payment_id: string | null;
  sync_attempts: number | null;
  last_sync_error: string | null;
  attempts: PaymentSyncAttempt[];
}

export interface PaymentListParams {
  page?: number;
  per_page?: number;
  status?: string;
  branch_id?: number | string;
  collector_id?: number | string;
  customer_id?: number | string;
  zoho_sync_status?: string;
  search?: string;
}

export interface CreatePaymentPayload {
  customer_id: number;
  collector_id?: number;
  payment_method_id: number;
  amount: number | string;
  currency?: string;
  allocations: { invoice_id: number; amount: number | string }[];
  external_reference?: string;
  notes?: string;
  visit_id?: number;
  promise_id?: number;
  latitude?: number;
  longitude?: number;
  gps_accuracy?: number;
  device_info?: string;
  idempotency_key?: string;
}

export interface CollectorWallet {
  id: number;
  collector_id: number;
  branch_id: number;
  currency: string;
  balance: string | number;
  pending_handover_balance?: string | number;
  last_transaction_at?: string | null;
  collector?: (Collector & { user?: CollectorUserSummary | null }) | null;
  branch?: BranchSummary | null;
}

export interface CollectorWalletTransaction {
  id: number;
  collector_wallet_id: number;
  collector_id: number;
  branch_id?: number | null;
  payment_id?: number | null;
  reversal_id?: number | null;
  type: string;
  amount: string | number;
  balance_before?: string | number | null;
  balance_after?: string | number | null;
  currency: string | null;
  reference?: string | null;
  notes?: string | null;
  created_by?: number | null;
  created_at?: string;
}

export interface ReceiptVerification {
  receipt_number: string;
  status: string;
  issued_at: string | null;
  amount: string | number | null;
  currency: string | null;
  customer?: {
    contact_name?: string | null;
    customer_number?: string | null;
  } | null;
  branch?: Pick<BranchSummary, "code" | "name_en" | "name_fa"> | null;
  payment_reference?: string | null;
}

export interface PaymentsSummary {
  groups: {
    status: string;
    zoho_sync_status: string | null;
    cnt: number;
    total_amount: string | number;
  }[];
  total_count: number;
  total_amount: string | number;
}

export interface DashboardSummary {
  role?: string;
  scope?: { branch_ids: number[] | null; collector_id: number | null };
  customers?: { total: number; unmapped: number };
  invoices?: { total: number; open: number; overdue: number; outstanding_amount: string | number };
  assignments?: { total: number; active: number };
  visits?: { today: number; total: number };
  promises?: { open: number; overdue: number };
  payments?: { total: number; confirmed: number; sync_failed: number };
  cash_custody?: { pending_handovers: number };
  generated_at?: string;
}

export type CashTransferType = "cashbox_transfer" | "bank_deposit";
export type CashTransferStatus =
  | "draft"
  | "submitted"
  | "approved"
  | "sent"
  | "received"
  | "reversed";

export interface CashboxTransfer {
  id: number;
  uuid: string;
  transfer_number: string | null;
  from_cashbox_id: number;
  to_cashbox_id: number | null;
  branch_id: number;
  type: CashTransferType;
  currency: string;
  amount: string | number;
  status: CashTransferStatus | string;
  bank_name: string | null;
  bank_reference: string | null;
  deposited_on: string | null;
  notes: string | null;
  created_at?: string;
  submitted_at?: string | null;
  approved_at?: string | null;
  sent_at?: string | null;
  received_at?: string | null;
  reversed_at?: string | null;
}

export interface CashboxTransferPayload {
  from_cashbox_id: number;
  to_cashbox_id?: number;
  amount: string | number;
  type?: CashTransferType;
  bank_name?: string;
  bank_reference?: string;
  deposited_on?: string;
  notes?: string;
}

export interface CashReconciliationRun {
  id: number;
  uuid: string;
  branch_id: number;
  cashbox_id: number;
  reconciliation_date: string;
  currency: string;
  opening_balance: string | number;
  credits: string | number;
  debits: string | number;
  expected_balance: string | number;
  counted_balance: string | number | null;
  variance_amount: string | number;
  status: string;
  created_at?: string;
}

export interface ZohoMappingConflict {
  classification: string;
  customer_id: number;
  name: string | null;
  locations: Array<{
    zoho_location_id: string;
    name: string | null;
    branch: Pick<BranchSummary, "id" | "code" | "name_en"> | null;
  }>;
  payments_count: number;
  assignments_count: number;
}

export interface ZohoLocationMappingReview {
  zoho_location_id: string;
  name: string;
  is_active: boolean;
  linked_branch: Pick<BranchSummary, "id" | "code" | "name_en"> | null;
  invoice_count: number;
  mapped_invoice_count: number;
  customer_count: number;
  payment_count: number;
  assignment_count: number;
  recommended_action: string;
  candidate_branch: Pick<BranchSummary, "id" | "code" | "name_en"> | null;
  confidence: string;
  decision: string | null;
}
