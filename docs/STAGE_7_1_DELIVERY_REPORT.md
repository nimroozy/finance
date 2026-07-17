# Stage 7.1 Delivery Report

**Date (UTC):** 2026-07-17  
**Branch:** `cursor/stage-7-1-ui-functional-repair`  
**Draft PR:** https://github.com/nimroozy/finance/pull/12  
**VPS:** `root@209.38.194.184` (`/opt/collection-system`)  
**Do not merge.** **Stage 8 CRM not started.**

> Deploy stamp / SHA / backup paths / financial counts are filled in the **Production deploy** section after VPS sync.

---

## Summary

Stage 7.1 closes the highest-impact UI ↔ API gaps from `docs/STAGE_7_1_FUNCTIONAL_AUDIT.md` and ships an operations workspace redesign:

- Server-side ticket/task/installation filters (priority, SLA state, assignee, search, unassigned, …)
- Lightweight `/pickers/*` endpoints + searchable Branch/Customer/User/Department pickers
- `allowed_transitions` on show resources; UI status actions constrained to allowed paths
- Attachment galleries with list + download on ticket/task detail
- WhatsApp conversation customer column fix (`contact_name`)
- Ops design system: WorkspaceHeader, ResponsiveTabs, DataTable mobile cards, SavedViewSelector
- System version / DeploymentInfo endpoint for ops visibility

---

## Explicit non-goals

- Does not merge PR #12 or prior stacked PRs
- Does not start Stage 8 CRM
- Does not modify payment, wallet, handover, cashbox, custody reversal, or Zoho reconciliation calculations
- Does not send live WhatsApp/Meta messages
- Does not delete SSH deploy keys

---

## Automated test results

| Suite | Command | Result |
|-------|---------|--------|
| Playwright Stage 7 + 7.1 | `npx playwright test e2e/stage7.spec.ts e2e/stage71-functional.spec.ts` | **22 passed** |
| PHPUnit Stage 7 + 7.1 | `php artisan test --filter='Stage7|Stage71'` | **28 passed** (154 assertions) |

### Playwright fix note

Finalization repaired brittle selectors (DataTable dual table/card DOM, ResponsiveTabs visibility, Customer vs Branch picker, Create vs Quick create). No product bug required for green mocked E2E.

---

## Production deploy

| Item | Value |
|------|-------|
| Deploy stamp | _pending deploy_ |
| SHA deployed | _pending deploy_ |
| Pre-backup | _pending_ |
| Post-backup | _pending_ |
| `.deployed-sha` | _pending_ |

### Financial / Stage 7 counts

| Metric | Pre | Post | Match |
|--------|-----|------|-------|
| payments | _ | _ | _ |
| cash_handover_requests | _ | _ | _ |
| collector_wallets | _ | _ | _ |
| branch_cashboxes | _ | _ | _ |
| payment_reversals | _ | _ | _ |
| whatsapp_connections | _ | _ | _ |
| tickets | _ | _ | _ |
| tasks | _ | _ | _ |
| installations | _ | _ | _ |

### Seeders (post-deploy)

| Seeder | Result |
|--------|--------|
| `Stage7OrgSeeder` | _pending_ |
| `Stage7SlaPolicySeeder` | _pending_ |
| `Stage7TicketTypeSeeder` | _pending_ |
| `Stage7TaskTemplateSeeder` | _pending_ |
| `Stage7EscalationRuleSeeder` | _pending_ |
| `RolePermissionSeeder` | _pending_ (also run by `deploy.sh`) |

### STAGE71 TEST records

| Entity | ID | Label |
|--------|----|-------|
| Ticket | _pending_ | STAGE71 TEST ticket - DO NOT USE |
| Task | _pending_ | STAGE71 TEST task - DO NOT USE |
| Installation | _pending_ | STAGE71 TEST installation - DO NOT USE |

### Health / DeploymentInfo

| Check | Result |
|-------|--------|
| `https://finance.mns.af/up` | _pending_ |
| Docker compose | _pending_ |
| DeploymentInfo / system-version | _pending_ |
| SSH key `~/.ssh/id_ed25519` | **KEY_KEPT** (never deleted) |

---

## Checklist

- [ ] Pre-deploy backup + financial/ticket counts
- [ ] `scripts/sync-to-vps.sh` + `scripts/deploy.sh`
- [ ] Stage 7 seeders re-run
- [ ] Write `.deployed-sha` (repo root + `backend/` for image)
- [ ] STAGE71 TEST records via tinker
- [ ] Post counts MATCH financials; ticket/task/install counts include STAGE71 rows
- [ ] Health + DeploymentInfo
- [ ] Post-deploy backup
- [ ] Docs committed + pushed; PR #12 not merged; Stage 8 not started

---

## References

- `docs/STAGE_7_1_FUNCTIONAL_AUDIT.md` — defect inventory + fix status
- `docs/STAGE_7_DELIVERY_REPORT.md` — Stage 7 baseline deploy
- `docs/STAGE_7_TICKETING_TASKS.md`, workflow docs, OpenAPI
