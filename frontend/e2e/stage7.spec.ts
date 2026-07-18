import { test, expect, type Page, type Route } from "@playwright/test";

const STAGE7_PERMISSIONS = [
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
  "installations.view",
  "installations.update",
  "reports.support",
  "reports.technical",
  "reports.management",
  "escalations.view",
  "attachments.upload",
  "whatsapp.view",
  "whatsapp.ticket_intake",
  "ticket_types.manage",
  "sla.manage",
  "task_templates.manage",
  "escalations.manage",
];

const MOCK_USER = {
  id: 1,
  name: "Ops Admin",
  email: "ops@finance.mns.af",
  username: "ops",
  locale: "en",
  status: "active",
  roles: ["Super Administrator"],
  permissions: STAGE7_PERMISSIONS,
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

const MOCK_TICKET = {
  id: 10,
  ticket_number: "TKT-0010",
  branch_id: 1,
  customer_id: 5,
  source: "manual",
  type_code: "support",
  subject: "No connectivity",
  description: "Customer offline",
  priority: "high",
  status: "new",
  primary_assignee: { id: 1, name: "Ops Admin" },
  response_due_at: "2026-07-17T18:00:00+00:00",
  resolution_due_at: "2026-07-18T18:00:00+00:00",
  created_at: "2026-07-17T10:00:00+00:00",
  sla_state: { breached_at: null },
};

const MOCK_TASK = {
  id: 20,
  task_number: "TSK-0020",
  branch_id: 1,
  title: "Site visit",
  description: "Check CPE",
  type: "field",
  priority: "high",
  status: "offered",
  ticket_id: 10,
  assignee_id: 1,
  assignee: { id: 1, name: "Ops Admin" },
  created_at: "2026-07-17T10:05:00+00:00",
};

const MOCK_INSTALLATION = {
  id: 30,
  installation_number: "INS-0030",
  branch_id: 1,
  status: "finance_review",
  contact_name: "Ahmad",
  phone: "+93700000000",
  requested_package: "20Mbps",
  created_at: "2026-07-17T09:00:00+00:00",
  tasks: [],
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

async function mockStage7Api(page: Page, user = MOCK_USER) {
  await page.addInitScript((u) => {
    window.localStorage.setItem(
      "auth-storage",
      JSON.stringify({ state: { token: "e2e-stage7-token", user: u }, version: 0 }),
    );
  }, user);

  let ticketsById: Record<number, typeof MOCK_TICKET> = {
    [MOCK_TICKET.id]: { ...MOCK_TICKET },
  };
  let task = { ...MOCK_TASK };

  await page.route("**/api/v1/**", async (route) => {
    const req = route.request();
    const url = req.url();
    const method = req.method();

    if (url.includes("/auth/me")) {
      await fulfillJson(route, user);
      return;
    }

    if (url.includes("/branches")) {
      await fulfillJson(
        route,
        [
          {
            id: 1,
            code: "KBL",
            name_en: "Kabul",
            name_fa: "کابل",
            province_en: "Kabul",
            province_fa: "کابل",
            phone: null,
            address: null,
            receipt_prefix: "KBL",
            is_active: true,
          },
        ],
        {
          current_page: 1,
          last_page: 1,
          per_page: 50,
          total: 1,
        },
      );
      return;
    }

    if (url.includes("/ticket-types")) {
      await fulfillJson(route, [
        { id: 1, code: "support", name_en: "Support", name_fa: "پشتیبانی", is_active: true },
      ]);
      return;
    }

    const ticketMatch = url.match(/\/tickets\/(\d+)(\/|$|\?)/);
    if (ticketMatch && method === "POST" && url.includes("/transition")) {
      const id = Number(ticketMatch[1]);
      const body = req.postDataJSON() as { status?: string };
      const current = ticketsById[id] ?? { ...MOCK_TICKET, id };
      ticketsById[id] = { ...current, status: body.status || "in_progress" };
      await fulfillJson(route, ticketsById[id]);
      return;
    }

    if (ticketMatch && method === "GET" && !url.includes("/transition")) {
      const id = Number(ticketMatch[1]);
      await fulfillJson(route, ticketsById[id] ?? { ...MOCK_TICKET, id });
      return;
    }

    if (url.includes("/tickets") && !ticketMatch) {
      if (method === "POST") {
        const body = req.postDataJSON() as Partial<typeof MOCK_TICKET>;
        const created = {
          ...MOCK_TICKET,
          id: 99,
          ticket_number: "TKT-0099",
          subject: body.subject || MOCK_TICKET.subject,
          status: "new",
        };
        ticketsById[99] = created;
        await fulfillJson(route, created);
        return;
      }
      await fulfillJson(route, Object.values(ticketsById), {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: Object.keys(ticketsById).length,
      });
      return;
    }

    if (url.includes("/tasks/my")) {
      await fulfillJson(route, [task], {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      });
      return;
    }

    if (url.match(/\/tasks\/\d+\/accept/) && method === "POST") {
      task = { ...task, status: "accepted" };
      await fulfillJson(route, task);
      return;
    }

    if (url.match(/\/tasks\/\d+\/complete/) && method === "POST") {
      task = { ...task, status: "completed" };
      await fulfillJson(route, task);
      return;
    }

    if (url.match(/\/tasks\/\d+\/start/) && method === "POST") {
      task = { ...task, status: "in_progress" };
      await fulfillJson(route, task);
      return;
    }

    if (url.match(/\/tasks\/\d+/) && method === "GET") {
      await fulfillJson(route, task);
      return;
    }

    if (url.includes("/tasks")) {
      await fulfillJson(route, [task], {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      });
      return;
    }

    if (url.includes("/installations")) {
      if (url.match(/\/installations\/\d+/)) {
        await fulfillJson(route, MOCK_INSTALLATION);
        return;
      }
      await fulfillJson(route, [MOCK_INSTALLATION], {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 1,
      });
      return;
    }

    if (url.includes("/operations/dashboard/support")) {
      await fulfillJson(route, {
        open: 4,
        waiting_customer: 1,
        breached: 2,
        resolved_today: 3,
        by_priority: { high: 2 },
      });
      return;
    }

    if (url.includes("/operations/dashboard/technical")) {
      await fulfillJson(route, {
        open_field: 5,
        travelling: 1,
        in_progress: 2,
        blocked: 1,
        completed_today: 4,
      });
      return;
    }

    if (url.includes("/operations/dashboard/noc")) {
      await fulfillJson(route, {
        open_escalations: 2,
        escalated_tickets: 1,
        sla_breached: 1,
      });
      return;
    }

    if (url.includes("/operations/dashboard/finance")) {
      await fulfillJson(route, {
        finance_review: 3,
        equipment_waiting: 1,
        finance_related_tickets: 2,
      });
      return;
    }

    if (url.includes("/operations/dashboard/manager")) {
      await fulfillJson(route, {
        support: { open: 4, waiting_customer: 1, breached: 2, resolved_today: 3 },
        technical: {
          open_field: 5,
          travelling: 1,
          in_progress: 2,
          blocked: 1,
          completed_today: 4,
        },
        noc: { open_escalations: 2, escalated_tickets: 1, sla_breached: 1 },
        finance: {
          finance_review: 3,
          equipment_waiting: 1,
          finance_related_tickets: 2,
        },
        installations_in_flight: 6,
        task_backlog: 8,
      });
      return;
    }

    if (url.includes("/work-logs")) {
      await fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      });
      return;
    }

    if (url.includes("/ticket-intake")) {
      await fulfillJson(route, [], {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      });
      return;
    }

    if (url.includes("/dashboard/summary")) {
      await fulfillJson(route, {
        role: "super_admin",
        customers: { total: 0, unmapped: 0 },
        invoices: { total: 0, open: 0, overdue: 0, outstanding_amount: 0 },
        generated_at: new Date().toISOString(),
      });
      return;
    }

    await fulfillJson(route, []);
  });
}

test.describe("stage 7 ticketing / tasks", () => {
  test("ticket list, create, and detail status transition", async ({ page }) => {
    await mockStage7Api(page);
    await page.goto("/en/tickets");
    await expect(page.getByRole("heading", { name: /Tickets/i })).toBeVisible();
    await expect(page.getByText("TKT-0010")).toBeVisible();

    await page.goto("/en/tickets/new");
    const form = page.locator("main form");
    await form.locator("select").nth(0).selectOption({ label: "Kabul" });
    await form.locator("select").nth(1).selectOption("support");
    await form.locator("input").first().fill("Fiber down");
    await form.getByRole("button", { name: /Create/i }).click();
    await expect(page).toHaveURL(/\/tickets\/99/);

    await page.goto("/en/tickets/10");
    await expect(page.getByText("TKT-0010")).toBeVisible();
    await page.locator("main select").first().selectOption("in_progress");
    await page.getByRole("button", { name: /Apply status/i }).click();
    await expect(page.getByText(/Status updated/i)).toBeVisible();
  });

  test("task accept/complete on mobile viewport", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await mockStage7Api(page);
    await page.goto("/en/tasks/20");
    await expect(page.getByRole("button", { name: /^Accept$/i })).toBeVisible();
    await page.getByRole("button", { name: /^Accept$/i }).click();
    await expect(page.getByText(/Task accepted/i)).toBeVisible();

    // After accept, start work then complete via mocked transitions
    await page.getByRole("button", { name: /Start work/i }).click();
    await expect(page.getByText(/Work started/i)).toBeVisible();
    await page.getByRole("button", { name: /^Complete$/i }).click();
    await expect(page.getByText(/Task completed/i)).toBeVisible();
  });

  test("support dashboard KPIs", async ({ page }) => {
    await mockStage7Api(page);
    await page.goto("/en/support/dashboard");
    await expect(page.getByRole("heading", { name: /Support dashboard/i })).toBeVisible();
    await expect(page.getByText("Open tickets")).toBeVisible();
    await expect(page.getByText("4")).toBeVisible();
  });

  test("Persian RTL tickets page", async ({ page }) => {
    await mockStage7Api(page);
    await page.goto("/fa/tickets");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expect(page.getByRole("heading", { name: /تیکت/ })).toBeVisible();
  });

  test("permission denial hides ops nav", async ({ page }) => {
    await mockStage7Api(page, LIMITED_USER);
    await page.goto("/en/dashboard");
    await expect(page.locator("body")).toBeVisible();
    await expect(page.getByRole("link", { name: /^Tickets$/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^Tasks$/i })).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^Installations$/i })).toHaveCount(0);
  });

  test("installations list", async ({ page }) => {
    await mockStage7Api(page);
    await page.goto("/en/installations");
    await expect(page.getByRole("heading", { name: /Installations/i })).toBeVisible();
    await expect(page.getByText("INS-0030")).toBeVisible();
    await expect(page.getByText("Ahmad")).toBeVisible();
  });
});
