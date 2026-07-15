# Routes and visits

## Route statuses

| Status | Meaning |
|--------|---------|
| `draft` | Editable; stops can be replaced |
| `published` | Ready for collector; cannot edit body |
| `in_progress` | Collector started the day |
| `completed` | Collector finished |
| `cancelled` | Cancelled with reason |

Flow: create (draft) → publish → start → complete. Cancel from draft/published as allowed by `RouteService`.

## Stops

Each stop: `customer_id`, optional `assignment_id`, ordered `sequence`.

Stop statuses:

| Status | When |
|--------|------|
| `pending` | Default |
| `completed` | Visit logged with this `route_stop_id` |
| `skipped` | Reserved / set by route ops |

Reorder: `PUT /routes/{id}/stops` (`routes.manage`), draft/published rules enforced in service.

## Visit outcomes

Canonical list: `CollectionVisit::OUTCOMES` (also `GET /visits/outcomes`).

| Code | EN label |
|------|----------|
| `customer_met` | Customer Met |
| `not_available` | Customer Not Available |
| `not_home` | Not Home |
| `no_answer` | No Answer |
| `phone_unreachable` | Phone Unreachable |
| `wrong_address` | Address Incorrect |
| `customer_moved` | Customer Moved |
| `refused` | Customer Refused Payment |
| `disputed_balance` | Customer Disputed Balance |
| `promise_to_pay` | Promise to Pay |
| `requested_manager` | Requested Manager |
| `requested_cancellation` | Requested Service Cancellation |
| `requested_review` | Requested Account Review |
| `business_closed` | Business Closed |
| `follow_up` | Follow-up Required |
| `other` | Other |

**Notes required:** `wrong_address`, `customer_moved`, `disputed_balance`, `other`.

**Promise payload required** when outcome is `promise_to_pay`.

**Follow-up date required** when `follow_up_required` (auto for `follow_up` outcome).

Escalation notify triggers for `requested_manager`, `disputed_balance`, or `escalation_flag=true`.

A successful visit on an active assignment sets assignment status to `in_progress` (from `assigned`/`accepted`) and updates `last_visit_at`.

## GPS mismatch flags

When both visit coords and customer coords exist, Haversine distance sets `distance_meters` and `gps_risk_level`:

| Level | Rule (defaults) |
|-------|-----------------|
| `ok` | under `COLLECTION_GPS_WARNING_METERS` (200 m) |
| `warning` | from 200 m up to under `COLLECTION_GPS_HIGH_RISK_METERS` (1000 m) |
| `high_risk` | 1000 m and above |

Report: `GET /reports/gps-mismatch` (`reports.visits`).

GPS is one-shot per visit; denied permission clears coordinates.

## Evidence uploads

- `POST /visits/{id}/files` — `evidence.upload`; file max 10 MB; `jpg,jpeg,png,pdf,webp`
- Stored at `storage/app/private/visit-evidence/{visit_id}/…` (`local` disk)
- `GET /files/{id}/download` — `evidence.view` + ownership/branch policy

No Stage 4 payment photos or receipt archives yet.
