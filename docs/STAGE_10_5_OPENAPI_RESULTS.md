# Stage 10.5 — OpenAPI Validation Results

## Commands run

```bash
# YAML syntax
npx --yes js-yaml docs/openapi.yaml

# OpenAPI semantic validation
npx --yes @redocly/cli lint docs/openapi.yaml
```

## Before repair

`js-yaml` failed with a real parse error:

```
YAMLException: bad indentation of a mapping entry (4831:17)
```

`@redocly/cli lint` (once the YAML was even parseable) reported **3 errors**:

```
[1] docs/openapi.yaml:4706:60 at #/paths/~1crm~1leads~1{id}/get/responses/200/follow_ups
    Property `follow_ups` is not expected here.
[2] docs/openapi.yaml:4706:72 at #/paths/~1crm~1leads~1{id}/get/responses/200/quotations
    Property `quotations` is not expected here.
[3] docs/openapi.yaml:4706:84 at #/paths/~1crm~1leads~1{id}/get/responses/200/allowed_transitions
    Property `allowed_transitions` is not expected here.
```

## Root causes found and fixed

1. **Structural corruption in `/crm/leads/{id}/convert`** — the POST operation's
   `requestBody` schema was missing its `template_code` property and its
   entire `responses:` block. Immediately after, three schema definitions
   (`CrmLeadCreateRequest`, `CrmLeadUpdateRequest`, `CrmQuotationCreateRequest`)
   were pasted **inside the `paths:` section** instead of `components:
   schemas:`, even though all three are referenced elsewhere via
   `$ref: '#/components/schemas/...'`. This is what caused the YAML
   indentation error (a stray `template_code:` line at the wrong nesting
   level) and would have broken every `$ref` to those three schemas if the
   file had parsed at all.

   Fix: moved the three schemas to `components.schemas` (their referenced
   location), and restored the convert operation's `template_code` property
   and `responses` block in place.

2. **Unquoted comma inside a YAML flow-mapping description** —
   `'200': { description: Lead detail with activities, follow_ups, quotations, allowed_transitions }`
   — in YAML flow-mapping (`{ }`) syntax, an unquoted comma is a **field
   separator**, not punctuation. This line was being parsed as four separate
   keys (`description`, `follow_ups`, `quotations`, `allowed_transitions`)
   instead of one `description` string containing commas — exactly the 3
   `@redocly/cli` errors above. Fixed by quoting the description string.
   Checked the whole file for the same pattern (unquoted comma inside a flow
   mapping) — this was the only occurrence.

## Contract updates for endpoints changed this stage

Updated to match the actual (verified-against-a-real-server) behavior:

- `/customers` — documented that `search` now also matches phone, mobile,
  whatsapp_number, and zoho_contact_id (was contact_name/company_name/
  customer_number/email only); added the new `sync_status` filter param.
- `/pickers/customers` — documented the full response field list including
  `branch` and `sync_status`; added `limit` param and 401/403 responses.
- `/pickers/users` — added the `role` and `limit` params.
- `/pickers/branches` — added `include_inactive` param, documented `is_active`
  in the response.
- `/pickers/collectors`, `/pickers/services`, `/pickers/equipment`,
  `/pickers/products` — **new paths**, did not exist in the spec before
  this stage because the endpoints themselves didn't exist.

## After repair

```
$ npx --yes js-yaml docs/openapi.yaml
(exit 0, no output — valid YAML)

$ npx --yes @redocly/cli lint docs/openapi.yaml
...
Woohoo! Your API description is valid. 🎉
You have 355 warnings.
```

**0 errors.** The remaining 355 warnings are all `recommended`-ruleset style
suggestions (missing `operationId` on ~120 legacy operations, missing a `4XX`
response on GET-only endpoints, missing `tags[].description`) — pre-existing
across the whole spec, not "malformed indentation or incorrectly nested
schemas," and not concentrated in the priority contract list (customer
search, customer details, pickers, payments, assignments, routes, visits,
promises, ownership conflicts, handovers). Not fixed in this pass — bringing
~120 operations up to full best-practice completeness is a larger, spec-wide
documentation task, not a contract-correctness bug.
