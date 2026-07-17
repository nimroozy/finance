import { apiFetch, apiUpload, toQuery } from "@/lib/api";
import type { PaginationMeta } from "@/lib/types";

export const TICKET_STATUSES = [
  "new",
  "triaged",
  "assigned",
  "in_progress",
  "waiting_customer",
  "waiting_finance",
  "waiting_noc",
  "waiting_technical",
  "waiting_equipment",
  "scheduled",
  "escalated",
  "resolved",
  "verification_pending",
  "closed",
  "cancelled",
  "reopened",
] as const;

export const TICKET_PRIORITIES = [
  "low",
  "normal",
  "medium",
  "high",
  "urgent",
  "critical",
] as const;

export const TICKET_SOURCES = [
  "manual",
  "whatsapp",
  "phone_call",
  "customer_portal",
  "sales",
  "finance",
  "noc",
  "technical",
  "monitoring",
  "installation",
  "radius",
  "email_future",
  "api",
  "internal",
] as const;

export type TicketStatus = (typeof TICKET_STATUSES)[number];
export type TicketPriority = (typeof TICKET_PRIORITIES)[number];
export type TicketSource = (typeof TICKET_SOURCES)[number];

export type AllowedTransition = {
  status: string;
  label: string;
  requires_reason?: boolean;
  required_fields?: string[];
  permission?: string | null;
};

export type AttachmentSummary = {
  id: number;
  uuid?: string;
  original_name: string;
  mime?: string | null;
  size?: number | null;
  kind?: string | null;
  virus_scan_status?: string | null;
  uploaded_by?: number | null;
  created_at?: string | null;
  download_url?: string | null;
};

export type TicketSlaState = {
  id?: number;
  ticket_id?: number;
  response_due_at?: string | null;
  resolution_due_at?: string | null;
  response_state?: string | null;
  resolution_state?: string | null;
  response_remaining_seconds?: number | null;
  resolution_remaining_seconds?: number | null;
  breached_at?: string | null;
  paused_at?: string | null;
  status?: string | null;
};

export type TicketWatcher = {
  id?: number;
  user_id?: number;
  name?: string;
};

export type TicketCustomer = {
  id: number;
  customer_number?: string | null;
  contact_name?: string | null;
  company_name?: string | null;
  phone?: string | null;
  mobile?: string | null;
  branch_id?: number | null;
};

export type Ticket = {
  id: number;
  ticket_number: string;
  branch_id: number;
  customer_id?: number | null;
  customer_number?: string | null;
  customer_phone?: string | null;
  source: string;
  type_code: string;
  category?: string | null;
  subject: string;
  description?: string | null;
  priority: string;
  severity?: string | null;
  impact?: string | null;
  status: string;
  primary_assignee_id?: number | null;
  assigned_department_id?: number | null;
  assigned_team_id?: number | null;
  response_due_at?: string | null;
  resolution_due_at?: string | null;
  first_response_at?: string | null;
  resolved_at?: string | null;
  closed_at?: string | null;
  reopened_count?: number;
  resolution_summary?: string | null;
  customer_confirmation?: boolean | null;
  whatsapp_conversation_id?: number | null;
  created_by?: number | null;
  updated_by?: number | null;
  created_at?: string;
  updated_at?: string;
  sla_state?: TicketSlaState | null;
  watchers?: TicketWatcher[];
  primary_assignee?: { id: number; name: string; email?: string } | null;
  customer?: TicketCustomer | null;
  branch?: { id: number; code: string; name_en?: string; name_fa?: string | null } | null;
  assigned_department?: { id: number; code: string; name_en?: string } | null;
  assigned_team?: { id: number; code?: string; name_en?: string } | null;
  ticket_type?: { id: number; code: string; name_en?: string; name_fa?: string | null } | null;
  tasks?: Array<{ id: number; task_number?: string; title?: string; status?: string }>;
  allowed_transitions?: AllowedTransition[];
  open_related?: Array<{
    id: number;
    ticket_number: string;
    subject: string;
    status: string;
    priority: string;
    branch_id: number;
  }>;
  recent_work_logs?: WorkLog[];
  attachments_summary?: AttachmentSummary[];
};

export type TicketType = {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
  default_department_code?: string | null;
  default_priority?: string | null;
  sla_policy_id?: number | null;
  requires_customer?: boolean;
  is_active?: boolean;
};

export type TicketListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  q?: string;
  status?: string;
  priority?: string;
  branch_id?: number | string;
  type_code?: string;
  department_id?: number | string;
  team_id?: number | string;
  assignee_id?: number | string;
  customer_id?: number | string;
  source?: string;
  sla_state?: string;
  created_from?: string;
  created_to?: string;
  updated_from?: string;
  updated_to?: string;
  overdue?: boolean | string | number;
  unassigned?: boolean | string | number;
  major_incident_id?: number | string;
  sort?: string;
};

export type TicketListMeta = PaginationMeta & {
  filters?: string[];
};

export type CreateTicketPayload = {
  branch_id: number;
  type_code: string;
  subject: string;
  description?: string;
  source?: string;
  priority?: string;
  severity?: string;
  impact?: string;
  customer_id?: number | null;
  customer_number?: string;
  customer_phone?: string;
  whatsapp_conversation_id?: number;
  category?: string;
  primary_assignee_id?: number;
  assigned_department_id?: number;
  assigned_team_id?: number;
};

export type WorkLog = {
  id: number;
  ticket_id?: number | null;
  task_id?: number | null;
  work_type?: string | null;
  internal_note?: string | null;
  customer_visible_note?: string | null;
  body?: string | null;
  duration_minutes?: number | null;
  started_at?: string | null;
  ended_at?: string | null;
  result?: string | null;
  follow_up_required?: boolean;
  created_at?: string;
  user_id?: number | null;
};

export type TicketIntakeSuggestion = {
  id: number;
  conversation_id?: number | null;
  branch_id?: number | null;
  customer_id?: number | null;
  status: string;
  ticket_id?: number | null;
  meta?: Record<string, unknown> | null;
  ticket?: Ticket | null;
};

export async function listTickets(params: TicketListParams = {}) {
  return apiFetch<Ticket[]>(
    `/tickets${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      search: params.search ?? params.q,
      status: params.status,
      priority: params.priority,
      branch_id: params.branch_id,
      type_code: params.type_code,
      department_id: params.department_id,
      team_id: params.team_id,
      assignee_id: params.assignee_id,
      customer_id: params.customer_id,
      source: params.source,
      sla_state: params.sla_state,
      created_from: params.created_from,
      created_to: params.created_to,
      updated_from: params.updated_from,
      updated_to: params.updated_to,
      overdue: params.overdue,
      unassigned: params.unassigned,
      major_incident_id: params.major_incident_id,
      sort: params.sort,
    })}`,
  );
}

export async function getTicket(id: number | string) {
  return apiFetch<Ticket>(`/tickets/${id}`);
}

export async function createTicket(payload: CreateTicketPayload) {
  return apiFetch<Ticket>("/tickets", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateTicket(
  id: number | string,
  payload: Partial<CreateTicketPayload> & {
    internal_notes?: string;
    customer_visible_notes?: string;
    resolution_summary?: string;
    whatsapp_conversation_id?: number | null;
  },
) {
  return apiFetch<Ticket>(`/tickets/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function transitionTicket(
  id: number | string,
  status: string,
  reason?: string,
  comment?: string,
) {
  return apiFetch<Ticket>(`/tickets/${id}/transition`, {
    method: "POST",
    body: JSON.stringify({ status, reason, comment }),
  });
}

export async function assignTicket(
  id: number | string,
  userId: number,
  reason?: string,
) {
  return apiFetch<Ticket>(`/tickets/${id}/assign`, {
    method: "POST",
    body: JSON.stringify({ user_id: userId, reason }),
  });
}

export async function resolveTicket(
  id: number | string,
  payload: { resolution_summary?: string; customer_confirmation?: boolean } = {},
) {
  return apiFetch<Ticket>(`/tickets/${id}/resolve`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function closeTicket(id: number | string, reason?: string) {
  return apiFetch<Ticket>(`/tickets/${id}/close`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function reopenTicket(id: number | string, reason?: string) {
  return apiFetch<Ticket>(`/tickets/${id}/reopen`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function listTicketTypes() {
  return apiFetch<TicketType[]>("/ticket-types");
}

export async function listAttachments(ticketId: number | string) {
  return apiFetch<AttachmentSummary[]>(`/tickets/${ticketId}/attachments`);
}

export async function uploadAttachment(
  ticketId: number | string,
  file: File,
  kind?: string,
) {
  const form = new FormData();
  form.append("file", file);
  form.append("attachable_type", "ticket");
  form.append("attachable_id", String(ticketId));
  if (kind) form.append("kind", kind);
  return apiUpload<{ attachment: AttachmentSummary; download_url?: string }>(
    "/attachments",
    form,
  );
}

export async function listWorkLogs(
  params: {
    ticket_id?: number | string;
    task_id?: number | string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<WorkLog[]>(
    `/work-logs${toQuery({
      ticket_id: params.ticket_id,
      task_id: params.task_id,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createWorkLog(payload: {
  ticket_id?: number;
  task_id?: number;
  work_type?: string;
  internal_note?: string;
  customer_visible_note?: string;
  body?: string;
  duration_minutes?: number;
  result?: string;
  follow_up_required?: boolean;
}) {
  return apiFetch<WorkLog>("/work-logs", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listTicketIntake(
  params: {
    status?: string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<TicketIntakeSuggestion[]>(
    `/ticket-intake${toQuery({
      status: params.status,
      page: params.page ?? 1,
      per_page: params.per_page ?? 50,
    })}`,
  );
}

export async function createTicketFromIntake(suggestionId: number | string) {
  return apiFetch<{ suggestion: TicketIntakeSuggestion; ticket: Ticket | null }>(
    `/ticket-intake/${suggestionId}/create-ticket`,
    { method: "POST" },
  );
}

export async function dismissTicketIntake(suggestionId: number | string) {
  return apiFetch<TicketIntakeSuggestion>(`/ticket-intake/${suggestionId}/dismiss`, {
    method: "POST",
  });
}
