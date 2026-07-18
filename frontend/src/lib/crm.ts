import { apiFetch, toQuery } from "@/lib/api";
import type { PaginationMeta } from "@/lib/types";

export const PIPELINE_STAGES = [
  "lead",
  "contacted",
  "qualified",
  "coverage_check",
  "site_survey",
  "quotation",
  "negotiation",
  "approved",
  "finance_review",
  "installation_request",
  "installation_scheduled",
  "installation_completed",
  "radius_activation",
  "zoho_customer",
  "active_customer",
  "after_sales",
  "lost",
  "cancelled",
] as const;

export const CUSTOMER_TYPES = [
  "residential",
  "business",
  "enterprise",
  "government",
] as const;

export const ACTIVITY_TYPES = [
  "call",
  "whatsapp",
  "sms",
  "email",
  "meeting",
  "visit",
  "site_survey",
  "demo",
  "follow_up",
  "internal_discussion",
] as const;

export const FOLLOW_UP_STATUSES = [
  "pending",
  "done",
  "overdue",
  "cancelled",
] as const;

export const QUOTATION_STATUSES = [
  "draft",
  "sent",
  "accepted",
  "rejected",
  "expired",
] as const;

export type PipelineStage = (typeof PIPELINE_STAGES)[number];
export type CustomerType = (typeof CUSTOMER_TYPES)[number];
export type ActivityType = (typeof ACTIVITY_TYPES)[number];
export type FollowUpStatus = (typeof FOLLOW_UP_STATUSES)[number];
export type QuotationStatus = (typeof QUOTATION_STATUSES)[number];

export type CrmUserRef = { id: number; name: string; email?: string };

export type LeadSource = {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
  is_active?: boolean;
};

export type Lead = {
  id: number;
  lead_number: string;
  branch_id: number;
  organization_id?: number | null;
  contact_id?: number | null;
  company?: string | null;
  contact_person?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  email?: string | null;
  address?: string | null;
  gps_lat?: number | string | null;
  gps_lng?: number | string | null;
  source_code?: string | null;
  campaign?: string | null;
  referral?: string | null;
  salesperson_id?: number | null;
  salesperson?: CrmUserRef | null;
  lead_value?: number | string | null;
  estimated_mrr?: number | string | null;
  expected_installation_fee?: number | string | null;
  interested_package?: string | null;
  customer_type?: string | null;
  pipeline_stage: string;
  status?: string;
  owner_id?: number | null;
  owner?: CrmUserRef | null;
  next_follow_up_at?: string | null;
  probability?: number | null;
  expected_close_date?: string | null;
  competitor?: string | null;
  lost_reason?: string | null;
  win_reason?: string | null;
  converted_customer_id?: number | null;
  installation_id?: number | null;
  opportunity_value?: number | string | null;
  notes?: string | null;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
  allowed_transitions?: string[];
  activities?: CrmActivity[];
  follow_ups?: FollowUp[];
  quotations?: Quotation[];
  stage_transitions?: LeadStageTransition[];
  duplicate_warning?: boolean | string | null;
};

export type LeadStageTransition = {
  id: number;
  lead_id: number;
  from_stage?: string | null;
  to_stage: string;
  reason?: string | null;
  notes?: string | null;
  changed_by?: number | null;
  created_at?: string;
};

export type CrmActivity = {
  id: number;
  lead_id: number;
  opportunity_id?: number | null;
  branch_id?: number;
  type: string;
  subject?: string | null;
  body?: string | null;
  performed_by?: number | null;
  performed_at?: string | null;
  customer_visible?: boolean;
  created_at?: string;
};

export type FollowUp = {
  id: number;
  lead_id: number;
  assigned_to?: number | null;
  due_at: string;
  status: string;
  notes?: string | null;
  completed_at?: string | null;
  created_at?: string;
  lead?: Pick<Lead, "id" | "lead_number" | "branch_id" | "contact_person"> | null;
};

export type Quotation = {
  id: number;
  quote_number: string;
  lead_id: number;
  branch_id: number;
  monthly_package?: number | string | null;
  installation_fee?: number | string | null;
  discount?: number | string | null;
  equipment_json?: unknown;
  taxes?: number | string | null;
  notes?: string | null;
  valid_until?: string | null;
  status: string;
  approved_by?: number | null;
  created_at?: string;
  updated_at?: string;
};

export type SiteSurvey = {
  id: number;
  lead_id: number;
  branch_id: number;
  gps_lat?: number | string | null;
  gps_lng?: number | string | null;
  los_status?: string | null;
  signal_estimate?: string | null;
  mounting_location?: string | null;
  power_availability?: string | null;
  equipment_recommendation?: string | null;
  technician_id?: number | null;
  survey_date?: string | null;
  customer_approved?: boolean | null;
  notes?: string | null;
  task_id?: number | null;
  task?: { id: number; task_number?: string; status?: string } | null;
  created_at?: string;
};

export type Opportunity = {
  id: number;
  lead_id: number;
  branch_id?: number;
  title?: string | null;
  stage?: string | null;
  probability?: number | null;
  value?: number | string | null;
  expected_close_date?: string | null;
  salesperson_id?: number | null;
  lost_reason?: string | null;
  win_reason?: string | null;
  created_at?: string;
};

export type SalesTarget = {
  id: number;
  branch_id: number;
  team_id?: number | null;
  user_id?: number | null;
  period_year: number;
  period_month: number;
  leads_target?: number | null;
  qualified_target?: number | null;
  won_target?: number | null;
  revenue_target?: number | string | null;
  installation_fee_target?: number | string | null;
  mrr_target?: number | string | null;
};

export type CrmDashboard = {
  leads_total: number;
  by_stage: Record<string, number>;
  open_follow_ups: number;
  overdue_follow_ups: number;
  quotes_accepted: number;
  converted: number;
  pipeline_value: number;
  estimated_mrr: number;
  targets: SalesTarget[];
};

export type LeadListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  branch_id?: number | string;
  pipeline_stage?: string;
  status?: string;
  salesperson_id?: number | string;
  owner_id?: number | string;
  source_code?: string;
  phone?: string;
};

export type CreateLeadPayload = {
  branch_id: number;
  company?: string;
  contact_person?: string;
  phone?: string;
  whatsapp?: string;
  email?: string;
  address?: string;
  gps_lat?: number;
  gps_lng?: number;
  source_code?: string;
  campaign?: string;
  referral?: string;
  salesperson_id?: number;
  owner_id?: number;
  lead_value?: number;
  estimated_mrr?: number;
  expected_installation_fee?: number;
  interested_package?: string;
  customer_type?: string;
  pipeline_stage?: string;
  next_follow_up_at?: string;
  probability?: number;
  expected_close_date?: string;
  competitor?: string;
  opportunity_value?: number;
  notes?: string;
};

export async function listLeadSources(activeOnly = true) {
  return apiFetch<LeadSource[]>(
    `/crm/lead-sources${toQuery({ active_only: activeOnly ? 1 : 0 })}`,
  );
}

export async function listLeads(params: LeadListParams = {}) {
  return apiFetch<Lead[]>(
    `/crm/leads${toQuery({
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
      search: params.search,
      branch_id: params.branch_id,
      pipeline_stage: params.pipeline_stage ?? params.status,
      salesperson_id: params.salesperson_id,
      owner_id: params.owner_id,
      source_code: params.source_code,
      phone: params.phone,
    })}`,
  );
}

export async function getLead(id: number | string) {
  return apiFetch<Lead>(`/crm/leads/${id}`);
}

export async function createLead(payload: CreateLeadPayload) {
  return apiFetch<Lead>("/crm/leads", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateLead(
  id: number | string,
  payload: Partial<CreateLeadPayload>,
) {
  return apiFetch<Lead>(`/crm/leads/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function transitionLead(
  id: number | string,
  pipelineStage: string,
  reason?: string,
  notes?: string,
) {
  return apiFetch<Lead>(`/crm/leads/${id}/transition`, {
    method: "POST",
    body: JSON.stringify({
      pipeline_stage: pipelineStage,
      reason,
      notes,
    }),
  });
}

export async function assignLead(
  id: number | string,
  ownerId: number,
  salespersonId?: number,
) {
  return apiFetch<Lead>(`/crm/leads/${id}/assign`, {
    method: "POST",
    body: JSON.stringify({
      owner_id: ownerId,
      salesperson_id: salespersonId,
    }),
  });
}

export async function convertLead(
  id: number | string,
  payload: {
    customer_id?: number;
    collector_id?: number;
    win_reason?: string;
    expand_template?: boolean;
    template_code?: string;
  } = {},
) {
  return apiFetch<{ lead: Lead; customer_id: number; installation_id?: number | null }>(
    `/crm/leads/${id}/convert`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
}

export async function getLeadTimeline(id: number | string) {
  return apiFetch<unknown>(`/crm/leads/${id}/timeline`);
}

export async function listActivities(
  params: {
    lead_id?: number | string;
    branch_id?: number | string;
    type?: string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<CrmActivity[]>(
    `/crm/activities${toQuery({
      lead_id: params.lead_id,
      branch_id: params.branch_id,
      type: params.type,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createActivity(payload: {
  lead_id: number;
  type: string;
  subject?: string;
  body?: string;
  opportunity_id?: number;
  performed_at?: string;
  customer_visible?: boolean;
  next_follow_up_at?: string;
}) {
  return apiFetch<CrmActivity>("/crm/activities", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listFollowUps(
  params: {
    lead_id?: number | string;
    status?: string;
    assigned_to?: number | string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<FollowUp[]>(
    `/crm/follow-ups${toQuery({
      lead_id: params.lead_id,
      status: params.status,
      assigned_to: params.assigned_to,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createFollowUp(payload: {
  lead_id: number;
  due_at: string;
  assigned_to?: number;
  notes?: string;
}) {
  return apiFetch<FollowUp>("/crm/follow-ups", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function completeFollowUp(id: number | string, notes?: string) {
  return apiFetch<FollowUp>(`/crm/follow-ups/${id}/complete`, {
    method: "POST",
    body: JSON.stringify({ notes }),
  });
}

export async function cancelFollowUp(id: number | string) {
  return apiFetch<FollowUp>(`/crm/follow-ups/${id}/cancel`, {
    method: "POST",
  });
}

export async function listQuotations(
  params: {
    lead_id?: number | string;
    branch_id?: number | string;
    status?: string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<Quotation[]>(
    `/crm/quotations${toQuery({
      lead_id: params.lead_id,
      branch_id: params.branch_id,
      status: params.status,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createQuotation(payload: {
  lead_id: number;
  monthly_package?: number;
  installation_fee?: number;
  discount?: number;
  taxes?: number;
  notes?: string;
  valid_until?: string;
  equipment_json?: unknown;
}) {
  return apiFetch<Quotation>("/crm/quotations", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function sendQuotation(id: number | string) {
  return apiFetch<Quotation>(`/crm/quotations/${id}/send`, { method: "POST" });
}

export async function acceptQuotation(id: number | string) {
  return apiFetch<Quotation>(`/crm/quotations/${id}/accept`, { method: "POST" });
}

export async function rejectQuotation(id: number | string, reason?: string) {
  return apiFetch<Quotation>(`/crm/quotations/${id}/reject`, {
    method: "POST",
    body: JSON.stringify({ reason }),
  });
}

export async function listSiteSurveys(
  params: {
    lead_id?: number | string;
    branch_id?: number | string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<SiteSurvey[]>(
    `/crm/site-surveys${toQuery({
      lead_id: params.lead_id,
      branch_id: params.branch_id,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createSiteSurvey(payload: {
  lead_id: number;
  gps_lat?: number;
  gps_lng?: number;
  los_status?: string;
  signal_estimate?: string;
  mounting_location?: string;
  power_availability?: string;
  equipment_recommendation?: string;
  technician_id?: number;
  survey_date?: string;
  customer_approved?: boolean;
  notes?: string;
}) {
  return apiFetch<SiteSurvey>("/crm/site-surveys", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function completeSiteSurvey(
  id: number | string,
  payload: Partial<{
    los_status: string;
    signal_estimate: string;
    mounting_location: string;
    power_availability: string;
    equipment_recommendation: string;
    customer_approved: boolean;
    notes: string;
    survey_date: string;
  }> = {},
) {
  return apiFetch<SiteSurvey>(`/crm/site-surveys/${id}/complete`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listOpportunities(
  params: {
    lead_id?: number | string;
    branch_id?: number | string;
    stage?: string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<Opportunity[]>(
    `/crm/opportunities${toQuery({
      lead_id: params.lead_id,
      branch_id: params.branch_id,
      stage: params.stage,
      page: params.page ?? 1,
      per_page: params.per_page ?? 50,
    })}`,
  );
}

export async function listSalesTargets(
  params: {
    branch_id?: number | string;
    period_year?: number | string;
    period_month?: number | string;
    page?: number;
    per_page?: number;
  } = {},
) {
  return apiFetch<SalesTarget[]>(
    `/crm/sales-targets${toQuery({
      branch_id: params.branch_id,
      period_year: params.period_year,
      period_month: params.period_month,
      page: params.page ?? 1,
      per_page: params.per_page ?? 15,
    })}`,
  );
}

export async function createSalesTarget(payload: {
  branch_id: number;
  team_id?: number;
  user_id?: number;
  period_year: number;
  period_month: number;
  leads_target?: number;
  qualified_target?: number;
  won_target?: number;
  revenue_target?: number;
  installation_fee_target?: number;
  mrr_target?: number;
}) {
  return apiFetch<SalesTarget>("/crm/sales-targets", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function getCrmDashboard(branchId?: number | string) {
  return apiFetch<CrmDashboard>(
    `/crm/dashboard${toQuery({ branch_id: branchId })}`,
  );
}

export type ZohoMirrorCustomer = {
  id: number;
  contact_name?: string | null;
  customer_number?: string | null;
  phone?: string | null;
  zoho_contact_id?: string | null;
  branch_id?: number | null;
};

export async function searchZohoMirrorCustomers(params: {
  q?: string;
  phone?: string;
  name?: string;
  zoho_contact_id?: string;
  branch_id?: number | string;
  limit?: number;
}) {
  return apiFetch<ZohoMirrorCustomer[]>(`/crm/customers/search-zoho-mirror${toQuery(params)}`);
}

export async function linkLeadZohoCustomer(
  leadId: number | string,
  customerId: number,
) {
  return apiFetch<{
    lead_id: number;
    converted_customer_id: number | null;
    customer: ZohoMirrorCustomer;
  }>(`/crm/leads/${leadId}/link-zoho-customer`, {
    method: "POST",
    body: JSON.stringify({ customer_id: customerId }),
  });
}

export function crmLeadsReportUrl(
  params: {
    branch_id?: number | string;
    pipeline_stage?: string;
    salesperson_id?: number | string;
    from?: string;
    to?: string;
  } = {},
) {
  const API_BASE =
    process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") || "/api/v1";
  return `${API_BASE}/crm/reports/leads${toQuery(params)}`;
}

export type LeadListMeta = PaginationMeta;
