import { apiFetch, apiUpload, toQuery } from "@/lib/api";

export type SupportDashboard = {
  open: number;
  waiting_customer: number;
  breached: number;
  resolved_today: number;
  by_priority?: Record<string, number>;
};

export type TechnicalDashboard = {
  open_field: number;
  travelling: number;
  in_progress: number;
  blocked: number;
  completed_today: number;
};

export type NocDashboard = {
  open_escalations: number;
  escalated_tickets: number;
  sla_breached: number;
};

export type FinanceDashboard = {
  finance_review: number;
  equipment_waiting: number;
  finance_related_tickets: number;
};

export type ManagerDashboard = {
  support: SupportDashboard;
  technical: TechnicalDashboard;
  noc: NocDashboard;
  finance: FinanceDashboard;
  installations_in_flight: number;
  task_backlog: number;
};

export type SlaPolicy = {
  id: number;
  name: string;
  branch_id?: number | null;
  ticket_type_code?: string | null;
  priority?: string | null;
  response_minutes: number;
  resolution_minutes: number;
  is_active?: boolean;
};

export type TaskTemplate = {
  id: number;
  code: string;
  name: string;
  workflow_type?: string | null;
  steps?: unknown;
  is_active?: boolean;
};

export type EscalationRule = {
  id: number;
  name: string;
  branch_id?: number | null;
  ticket_type_code?: string | null;
  priority?: string | null;
  department_code?: string | null;
  trigger_type?: string | null;
  trigger_after_minutes?: number | null;
  from_status?: string | null;
  escalate_to_role?: string | null;
  escalate_to_department_code?: string | null;
  escalate_to_user_id?: number | null;
  notify_whatsapp?: boolean;
  is_active?: boolean;
  sort_order?: number;
};

export type OperationsSearchResult = {
  tickets?: unknown[];
  tasks?: unknown[];
  installations?: unknown[];
};

export type CustomerTimeline = {
  tickets?: unknown[];
  tasks?: unknown[];
  installations?: unknown[];
  events?: unknown[];
};

export async function getSupportDashboard(branchId?: number | string) {
  return apiFetch<SupportDashboard>(
    `/operations/dashboard/support${toQuery({ branch_id: branchId })}`,
  );
}

export async function getTechnicalDashboard(branchId?: number | string) {
  return apiFetch<TechnicalDashboard>(
    `/operations/dashboard/technical${toQuery({ branch_id: branchId })}`,
  );
}

export async function getNocDashboard(branchId?: number | string) {
  return apiFetch<NocDashboard>(
    `/operations/dashboard/noc${toQuery({ branch_id: branchId })}`,
  );
}

export async function getFinanceDashboard(branchId?: number | string) {
  return apiFetch<FinanceDashboard>(
    `/operations/dashboard/finance${toQuery({ branch_id: branchId })}`,
  );
}

export async function getManagerDashboard(branchId?: number | string) {
  return apiFetch<ManagerDashboard>(
    `/operations/dashboard/manager${toQuery({ branch_id: branchId })}`,
  );
}

export async function operationsSearch(q: string) {
  return apiFetch<OperationsSearchResult>(`/operations/search${toQuery({ q })}`);
}

export async function reportTicketsOps(params: Record<string, string | number | undefined> = {}) {
  return apiFetch<unknown>(`/operations/reports/tickets${toQuery(params)}`);
}

export async function reportTasksOps(params: Record<string, string | number | undefined> = {}) {
  return apiFetch<unknown>(`/operations/reports/tasks${toQuery(params)}`);
}

export async function getCustomerTimeline(customerId: number | string) {
  return apiFetch<CustomerTimeline>(`/customers/${customerId}/timeline`);
}

export async function listSlaPolicies() {
  return apiFetch<SlaPolicy[]>("/sla-policies");
}

export async function createSlaPolicy(payload: Partial<SlaPolicy> & {
  name: string;
  response_minutes: number;
  resolution_minutes: number;
}) {
  return apiFetch<SlaPolicy>("/sla-policies", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateSlaPolicy(id: number | string, payload: Partial<SlaPolicy>) {
  return apiFetch<SlaPolicy>(`/sla-policies/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function listTaskTemplates() {
  return apiFetch<TaskTemplate[]>("/task-templates");
}

export async function createTaskTemplate(payload: {
  code: string;
  name: string;
  workflow_type?: string;
  steps?: unknown;
  is_active?: boolean;
}) {
  return apiFetch<TaskTemplate>("/task-templates", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateTaskTemplate(
  id: number | string,
  payload: Partial<TaskTemplate>,
) {
  return apiFetch<TaskTemplate>(`/task-templates/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function listEscalationRules() {
  return apiFetch<EscalationRule[]>("/escalation-rules");
}

export async function createEscalationRule(
  payload: Partial<EscalationRule> & { name: string },
) {
  return apiFetch<EscalationRule>("/escalation-rules", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateEscalationRule(
  id: number | string,
  payload: Partial<EscalationRule>,
) {
  return apiFetch<EscalationRule>(`/escalation-rules/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function createTicketType(payload: {
  code: string;
  name_en: string;
  name_fa?: string;
  default_priority?: string;
  is_active?: boolean;
}) {
  return apiFetch<unknown>("/ticket-types", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateTicketType(
  id: number | string,
  payload: Record<string, unknown>,
) {
  return apiFetch<unknown>(`/ticket-types/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function uploadAttachment(params: {
  file: File;
  attachable_type: "ticket" | "task" | "installation";
  attachable_id: number | string;
  kind?: string;
}) {
  const form = new FormData();
  form.append("file", params.file);
  form.append("attachable_type", params.attachable_type);
  form.append("attachable_id", String(params.attachable_id));
  if (params.kind) form.append("kind", params.kind);
  return apiUpload<{ attachment: unknown; download_url?: string }>("/attachments", form);
}

export type SystemVersionInfo = {
  app_name?: string;
  stage?: string | null;
  git_sha?: string | null;
  commit_sha?: string | null;
  branch?: string | null;
  build_timestamp?: string | null;
  deployment_timestamp?: string | null;
  backend_version?: string | null;
  frontend_version?: string | null;
  migration_batch?: number | null;
  latest_migration?: string | null;
  php_version?: string | null;
  laravel_version?: string | null;
};

export async function getVersion() {
  return apiFetch<SystemVersionInfo>("/system/version");
}

export type WhatsAppConnectionStatus = {
  connected?: boolean;
  status?: string;
  paused?: boolean;
  phone_number_id?: string | null;
  business_account_id?: string | null;
  last_error?: string | null;
};

export async function getWhatsAppStatus() {
  return apiFetch<WhatsAppConnectionStatus>("/whatsapp/status");
}
