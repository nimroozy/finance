/**
 * Stage 7.1 functional UI flows with mocked APIs.
 *
 * Real API / role-matrix tests are covered separately by backend Feature tests:
 * - Stage71TicketFiltersTest
 * - Stage71PickersTest
 * - Stage71TransitionsTest
 * Authenticated live VPS verification remains an ops checklist item.
 */
import { test, expect, type Page, type Route } from "@playwright/test";

const PERMS = [
  "dashboard.view",
  "tickets.view",
  "tickets.create",
  "tickets.update",
  "tickets.assign",
  "tickets.resolve",
  "tickets.close",
  "tickets.reopen",
  "tasks.view",
  "tasks.accept",
  "tasks.complete",
  "tasks.create",
  "attachments.upload",
  "whatsapp.view",
  "whatsapp.ticket_intake",
];

const USER = {
  id: 1,
  name: "Ops Admin",
  email: "ops@finance.mns.af",
  username: "ops",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: PERMS,
  force_password_change: false,
  branches: [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }],
};

async function fulfillJson(route: Route, data: unknown, meta?: Record<string, unknown>) {
  await route.fulfill({
    status: 200,
    contentType: "application/json",
    body: JSON.stringify({ success: true, data, ...(meta ? { meta } : {}) }),
  });
}

async function mockStage71(page: Page) {
  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage71", user: u }, version: 0 }),
    );
  }, USER);

  const ticket = {
    id: 10,
    ticket_number: "TKT-0010",
    branch_id: 1,
    customer_id: 5,
    source: "manual",
    type_code: "support",
    subject: "No connectivity",
    description: "Offline",
    priority: "high",
    status: "new",
    primary_assignee: { id: 1, name: "Ops Admin" },
    customer: { id: 5, customer_number: "C-100", contact_name: "Ahmad", phone: "+93700" },
    branch: { id: 1, code: "KBL", name_en: "Kabul" },
    assigned_department: { id: 1, code: "SUP", name_en: "Support" },
    created_at: "2026-07-17T10:00:00+00:00",
    updated_at: "2026-07-17T11:00:00+00:00",
    sla_state: { response_state: "running", resolution_state: "running" },
    allowed_transitions: [
      { status: "in_progress", label: "In Progress", requires_reason: false },
      { status: "cancelled", label: "Cancelled", requires_reason: true },
    ],
    tasks: [],
    open_related: [{ id: 11, ticket_number: "TKT-0011", subject: "Related", status: "new", priority: "normal", branch_id: 1 }],
    attachments_summary: [],
    recent_work_logs: [],
  };

  let attachments = [
    {
      id: 1,
      uuid: "att-1",
      original_name: "before.jpg",
      download_url: "/api/v1/attachments/att-1/download",
      created_at: "2026-07-17T10:00:00+00:00",
    },
  ];

  const listCalls: string[] = [];

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const url = req.url();
    const method = req.method();

    if (url.includes("/auth/me")) return fulfillJson(route, USER);

    if (url.includes("/pickers/customers")) {
      return fulfillJson(route, [
        {
          id: 5,
          customer_number: "C-100",
          contact_name: "Ahmad Karimi",
          phone: "+93700000000",
          mobile: "+93700000000",
          branch_id: 1,
        },
      ]);
    }
    if (url.includes("/pickers/branches")) {
      return fulfillJson(route, [{ id: 1, code: "KBL", name_en: "Kabul", name_fa: "کابل" }]);
    }
    if (url.includes("/pickers/users")) {
      return fulfillJson(route, [{ id: 1, name: "Ops Admin", email: "ops@finance.mns.af" }]);
    }
    if (url.includes("/pickers/departments")) {
      return fulfillJson(route, [{ id: 1, code: "SUP", name_en: "Support" }]);
    }
    if (url.includes("/pickers/teams")) {
      return fulfillJson(route, []);
    }

    if (url.includes("/ticket-types")) {
      return fulfillJson(route, [
        { id: 1, code: "support", name_en: "Support", name_fa: "پشتیبانی", is_active: true },
      ]);
    }

    if (url.includes("/tickets/") && url.includes("/attachments") && method === "GET") {
      return fulfillJson(route, attachments);
    }

    if (url.includes("/attachments") && method === "POST") {
      attachments = [
        ...attachments,
        {
          id: attachments.length + 1,
          uuid: `att-${attachments.length + 1}`,
          original_name: "upload.bin",
          download_url: "/api/v1/attachments/att-x/download",
          created_at: new Date().toISOString(),
        },
      ];
      return fulfillJson(route, { attachment: attachments.at(-1), download_url: attachments.at(-1)?.download_url });
    }

    if (url.match(/\/tickets\/\d+\/transition/) && method === "POST") {
      const body = req.postDataJSON() as { status?: string };
      ticket.status = body.status || ticket.status;
      ticket.allowed_transitions = [
        { status: "resolved", label: "Resolved", requires_reason: false },
      ];
      return fulfillJson(route, ticket);
    }

    if (url.match(/\/tickets\/\d+$/) && method === "GET") {
      return fulfillJson(route, ticket);
    }

    if (url.includes("/tickets") && method === "POST") {
      const body = req.postDataJSON() as { subject?: string; customer_id?: number };
      return fulfillJson(route, {
        ...ticket,
        id: 99,
        ticket_number: "TKT-0099",
        subject: body.subject || "New",
        customer_id: body.customer_id ?? null,
      });
    }

    if (url.includes("/tickets") && method === "GET") {
      listCalls.push(url);
      const u = new URL(url);
      let rows = [ticket];
      if (u.searchParams.get("priority") === "low") rows = [];
      if (u.searchParams.get("status") === "waiting_customer") rows = [];
      if (u.searchParams.get("sla_state") === "breached") rows = [];
      return fulfillJson(route, rows, {
        current_page: 1,
        last_page: 1,
        per_page: Number(u.searchParams.get("per_page") || 15),
        total: rows.length,
      });
    }

    if (url.includes("/work-logs")) {
      return fulfillJson(route, [], { current_page: 1, last_page: 1, per_page: 15, total: 0 });
    }

    if (url.includes("/dashboard/summary")) {
      return fulfillJson(route, {
        role: "super_admin",
        customers: { total: 0, unmapped: 0 },
        invoices: { total: 0, open: 0, overdue: 0, outstanding_amount: 0 },
        generated_at: new Date().toISOString(),
      });
    }

    return fulfillJson(route, []);
  });

  return { listCalls, getAttachments: () => attachments };
}

test.describe("stage 7.1 functional (mocked)", () => {
  test("URL filters are server-side and persist in query string", async ({ page }) => {
    const ctx = await mockStage71(page);
    await page.goto("/en/tickets?status=waiting_customer&priority=high&sla_state=breached&per_page=25");
    await expect(page.getByRole("heading", { name: /Tickets/i })).toBeVisible();
    await expect(page).toHaveURL(/status=waiting_customer/);
    await expect(page).toHaveURL(/priority=high/);
    await expect(page).toHaveURL(/sla_state=breached/);
    await expect(page).toHaveURL(/per_page=25/);

    await expect.poll(() => ctx.listCalls.some((u) => u.includes("status=waiting_customer"))).toBeTruthy();
    await expect.poll(() => ctx.listCalls.some((u) => u.includes("priority=high"))).toBeTruthy();
    await expect.poll(() => ctx.listCalls.some((u) => u.includes("sla_state=breached"))).toBeTruthy();
  });

  test("saved view writes URL params without client priority filtering", async ({ page }) => {
    const ctx = await mockStage71(page);
    await page.goto("/en/tickets");
    await page.getByRole("button", { name: /SLA Breached/i }).click();
    await expect(page).toHaveURL(/sla_state=breached/);
    await expect.poll(() => ctx.listCalls.some((u) => u.includes("sla_state=breached"))).toBeTruthy();
  });

  test("customer search create flow uses picker not raw IDs", async ({ page }) => {
    await mockStage71(page);
    await page.goto("/en/tickets/new");
    await expect(page.getByText(/Customer ID/i)).toHaveCount(0);

    // Customer searchable picker
    const customerInput = page.getByPlaceholder(/Type to search|Search/i).first();
    await customerInput.fill("Ahmad");
    await page.getByRole("option", { name: /Ahmad/i }).click().catch(async () => {
      // Fallback: click listbox item text
      await page.getByText(/Ahmad Karimi/i).click();
    });

    await page.locator("main select").filter({ hasText: /Support|Type/i }).first().selectOption("support").catch(async () => {
      await page.locator("main select").first().selectOption("support");
    });

    await page.locator("main form input[required]").last().fill("Fiber cut");

    await page.getByRole("button", { name: /Create/i }).click();
    await expect(page).toHaveURL(/\/tickets\/99/);
  });

  test("detail transitions use allowed_transitions only", async ({ page }) => {
    await mockStage71(page);
    await page.goto("/en/tickets/10");
    await expect(page.getByRole("button", { name: /In Progress/i })).toBeVisible();
    await expect(page.getByRole("button", { name: /Cancelled/i })).toBeVisible();
    // Full enum statuses like "verification pending" should not appear as free-form select
    await expect(page.locator("main select").filter({ hasText: /verification pending/i })).toHaveCount(0);

    await page.getByRole("button", { name: /In Progress/i }).click();
    await page.getByRole("button", { name: /Confirm/i }).click();
    await expect(page.getByText(/Status updated/i)).toBeVisible();
  });

  test("attachments gallery loads from API and refresh after upload", async ({ page }) => {
    const ctx = await mockStage71(page);
    await page.goto("/en/tickets/10");
    // Open attachments tab (desktop tabs or mobile select)
    const tabsSelect = page.locator("#responsive-tabs-select");
    if (await tabsSelect.count()) {
      await tabsSelect.selectOption("attachments");
    } else {
      await page.getByRole("tab", { name: /Attachments/i }).click();
    }
    await expect(page.getByText("before.jpg")).toBeVisible();

    const fileInput = page.locator('input[type="file"]');
    await fileInput.setInputFiles({
      name: "upload.bin",
      mimeType: "application/octet-stream",
      buffer: Buffer.from("hello"),
    });
    await expect.poll(() => ctx.getAttachments().length).toBeGreaterThan(1);
    await expect(page.getByText(/upload\.bin|Attachment uploaded/i)).toBeVisible();
  });
});
