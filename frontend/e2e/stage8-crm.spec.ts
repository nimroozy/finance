import { test, expect, type Page, type Route } from "@playwright/test";
import { clickConfirmDialog, clickMainAction } from "./helpers";

const STAGE8_PERMISSIONS = [
  "dashboard.view",
  "crm.leads.view",
  "crm.leads.create",
  "crm.leads.update",
  "crm.leads.assign",
  "crm.leads.convert",
  "crm.activities.manage",
  "crm.follow_ups.manage",
  "crm.coverage.manage",
  "crm.surveys.view",
  "crm.surveys.manage",
  "crm.quotations.view",
  "crm.quotations.create",
  "crm.quotations.approve",
  "crm.pipeline.manage",
  "crm.targets.view",
  "crm.targets.manage",
  "crm.reports.view",
];

const MOCK_USER = {
  id: 1,
  name: "CRM Admin",
  email: "crm@finance.mns.af",
  username: "crm",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: STAGE8_PERMISSIONS,
  force_password_change: false,
  branches: [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }],
};

const LIMITED_USER = {
  ...MOCK_USER,
  id: 2,
  name: "Limited",
  email: "limited@finance.mns.af",
  permissions: ["dashboard.view"],
  roles: ["Viewer"],
};

const MOCK_LEAD = {
  id: 10,
  lead_number: "LEAD-000010",
  branch_id: 1,
  company: "Acme ISP" as string | null,
  contact_person: "Ahmad",
  phone: "+93700000000" as string | null,
  email: "ahmad@example.com",
  source_code: "whatsapp",
  pipeline_stage: "lead",
  status: "lead",
  owner: { id: 1, name: "CRM Admin" },
  salesperson: { id: 1, name: "CRM Admin" },
  lead_value: 1000,
  estimated_mrr: 500,
  opportunity_value: 1000,
  interested_package: "20Mbps",
  created_at: "2026-07-17T10:00:00+00:00",
  updated_at: "2026-07-17T11:00:00+00:00",
  allowed_transitions: ["contacted", "qualified", "lost", "cancelled"],
  activities: [],
  follow_ups: [],
  quotations: [],
  stage_transitions: [],
};

const MOCK_FOLLOW_UP = {
  id: 20,
  lead_id: 10,
  due_at: "2026-07-18T10:00:00+00:00",
  status: "pending",
  notes: "Call back",
  lead: {
    id: 10,
    lead_number: "LEAD-000010",
    branch_id: 1,
    contact_person: "Ahmad",
  },
};

const MOCK_QUOTE = {
  id: 30,
  quote_number: "QTE-000030",
  lead_id: 10,
  branch_id: 1,
  monthly_package: 1200,
  installation_fee: 500,
  status: "draft",
  created_at: "2026-07-17T10:00:00+00:00",
  updated_at: "2026-07-17T10:00:00+00:00",
};

const MOCK_SURVEY = {
  id: 40,
  lead_id: 10,
  branch_id: 1,
  los_status: "clear",
  signal_estimate: "good",
  customer_approved: false,
  survey_date: "2026-07-17",
  created_at: "2026-07-17T09:00:00+00:00",
};

const MOCK_TARGET = {
  id: 50,
  branch_id: 1,
  period_year: 2026,
  period_month: 7,
  leads_target: 20,
  qualified_target: 10,
  won_target: 5,
  revenue_target: 50000,
  mrr_target: 10000,
};

const MOCK_DASHBOARD = {
  leads_total: 1,
  by_stage: { lead: 1 },
  open_follow_ups: 1,
  overdue_follow_ups: 0,
  quotes_accepted: 0,
  converted: 0,
  pipeline_value: 1000,
  estimated_mrr: 500,
  targets: [MOCK_TARGET],
};

async function fulfillJson(route: Route, data: unknown, meta?: Record<string, number>) {
  await route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({
      success: true,
      data,
      ...(meta ? { meta } : {}),
    }),
  });
}

async function mockStage8Api(page: Page, user = MOCK_USER) {
  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage8-token", user: u }, version: 0 }),
    );
  }, user);

  const leadsById: Record<number, typeof MOCK_LEAD> = {
    [MOCK_LEAD.id]: { ...MOCK_LEAD },
  };
  let quote = { ...MOCK_QUOTE };
  let followUp = { ...MOCK_FOLLOW_UP };

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const url = req.url();

    if (url.includes("/auth/me") || url.endsWith("/me")) {
      await fulfillJson(route, user);
      return;
    }

    if (url.includes("/crm/lead-sources")) {
      await fulfillJson(route, [
        { id: 1, code: "whatsapp", name_en: "WhatsApp", name_fa: "واتساپ", is_active: true },
        { id: 2, code: "walk_in", name_en: "Walk-in", name_fa: "حضوری", is_active: true },
      ]);
      return;
    }

    if (url.includes("/crm/dashboard")) {
      await fulfillJson(route, MOCK_DASHBOARD);
      return;
    }

    if (url.includes("/crm/reports/leads")) {
      await route.fulfill({
        status: 200,
        contentType: "text/csv",
        body: "lead_number,stage\nLEAD-000010,lead\n",
      });
      return;
    }

    if (url.includes("/crm/sales-targets") && method === "GET") {
      await fulfillJson(route, [MOCK_TARGET], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/crm/sales-targets") && method === "POST") {
      await fulfillJson(route, { ...MOCK_TARGET, id: 51 }, undefined);
      return;
    }

    if (url.includes("/crm/follow-ups") && url.includes("/complete") && method === "POST") {
      followUp = { ...followUp, status: "done" };
      await fulfillJson(route, followUp);
      return;
    }

    if (url.includes("/crm/follow-ups") && method === "GET") {
      await fulfillJson(route, [followUp], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/crm/quotations") && url.includes("/send") && method === "POST") {
      quote = { ...quote, status: "sent" };
      await fulfillJson(route, quote);
      return;
    }

    if (url.includes("/crm/quotations") && method === "GET") {
      await fulfillJson(route, [quote], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/crm/site-surveys") && method === "GET") {
      await fulfillJson(route, [MOCK_SURVEY], {
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      });
      return;
    }

    const leadMatch = url.match(/\/crm\/leads\/(\d+)(\/|$|\?)/);
    if (leadMatch && method === "GET" && !url.includes("/timeline")) {
      const id = Number(leadMatch[1]);
      await fulfillJson(route, leadsById[id] ?? { ...MOCK_LEAD, id, lead_number: `LEAD-${String(id).padStart(6, "0")}` });
      return;
    }

    if (leadMatch && url.includes("/transition") && method === "POST") {
      const id = Number(leadMatch[1]);
      const body = req.postDataJSON() as { pipeline_stage?: string };
      const current = leadsById[id] ?? { ...MOCK_LEAD, id };
      const updated = {
        ...current,
        pipeline_stage: body.pipeline_stage || "contacted",
        status: body.pipeline_stage || "contacted",
        allowed_transitions: ["qualified", "coverage_check", "lost", "cancelled"],
      };
      leadsById[id] = updated;
      await fulfillJson(route, updated);
      return;
    }

    if (url.includes("/crm/leads") && method === "POST" && !leadMatch) {
      const body = req.postDataJSON() as Record<string, unknown>;
      const created = {
        ...MOCK_LEAD,
        id: 99,
        lead_number: "LEAD-000099",
        contact_person: String(body.contact_person || "New Contact"),
        company: body.company ? String(body.company) : null,
        phone: body.phone ? String(body.phone) : null,
        allowed_transitions: ["contacted", "qualified", "lost", "cancelled"],
      };
      leadsById[99] = created;
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({ success: true, data: created }),
      });
      return;
    }

    if (url.includes("/crm/leads") && method === "GET") {
      const rows = Object.values(leadsById);
      await fulfillJson(route, rows, {
        current_page: 1,
        last_page: 1,
        total: rows.length,
        per_page: 15,
      });
      return;
    }

    if (url.includes("/pickers/") || url.includes("/branches")) {
      await fulfillJson(route, [
        { id: 1, label: "Kabul", code: "KBL", name_en: "Kabul", name_fa: "کابل" },
      ], { current_page: 1, last_page: 1, total: 1, per_page: 15 });
      return;
    }

    await fulfillJson(route, []);
  });
}

test.describe("Stage 8 CRM", () => {
  test("CRM nav and core pages render", async ({ page }) => {
    await mockStage8Api(page);

    await page.goto("/en/crm/dashboard");
    await expect(page.getByRole("heading", { name: /CRM dashboard/i })).toBeVisible();
    await expect(page.getByText(/Total leads/i).first()).toBeVisible();

    await page.goto("/en/crm/pipeline");
    await expect(page.getByRole("heading", { name: /Pipeline/i })).toBeVisible();
    await expect(page.locator(":visible", { hasText: "LEAD-000010" }).first()).toBeVisible();

    await page.goto("/en/crm/leads");
    await expect(page.getByRole("heading", { name: /^Leads$/i })).toBeVisible();
    await expect(page.locator("a:visible", { hasText: /LEAD-000010/ }).first()).toBeVisible();

    await page.goto("/en/crm/leads/new");
    await expect(page.getByRole("heading", { name: /Create lead/i })).toBeVisible();
    await page.getByRole("textbox").first().fill("New Contact");
    await clickMainAction(page, /Create lead/i);
    await expect(page).toHaveURL(/\/crm\/leads\/99/);

    await page.goto("/en/crm/leads/10");
    await expect(page.getByRole("heading", { name: /LEAD-000010/ })).toBeVisible();
    await page.getByRole("button", { name: /^Contacted$/i }).click();
    await clickConfirmDialog(page);
    await expect(page.getByText(/Stage updated/i)).toBeVisible();

    await page.goto("/en/crm/follow-ups");
    await expect(page.getByRole("heading", { name: /Follow-ups/i })).toBeVisible();
    await expect(page.locator(":visible", { hasText: /LEAD-000010/ }).first()).toBeVisible();

    await page.goto("/en/crm/quotations");
    await expect(page.getByRole("heading", { name: /Quotations/i })).toBeVisible();
    await expect(page.locator(":visible", { hasText: /QTE-000030/ }).first()).toBeVisible();

    await page.goto("/en/crm/surveys");
    await expect(page.getByRole("heading", { name: /Site surveys/i })).toBeVisible();

    await page.goto("/en/crm/targets");
    await expect(page.getByRole("heading", { name: /Sales targets/i })).toBeVisible();

    await page.goto("/en/crm/reports");
    await expect(page.getByRole("heading", { name: /CRM reports/i })).toBeVisible();
  });

  test("Persian CRM leads heading", async ({ page }) => {
    await mockStage8Api(page);
    await page.goto("/fa/crm/leads");
    await expect(page.getByRole("heading", { name: /سرنخ/ })).toBeVisible();
  });

  test("limited user does not see CRM nav links", async ({ page }) => {
    await mockStage8Api(page, LIMITED_USER);
    await page.goto("/en/dashboard");
    await expect(page.getByRole("link", { name: /^Leads$/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^Pipeline$/i })).toHaveCount(0);
  });
});
