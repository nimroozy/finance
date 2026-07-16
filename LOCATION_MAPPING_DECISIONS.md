# LOCATION_MAPPING_DECISIONS.md

**Stage:** 5.1 P1 — Controlled location mapping  
**Date (UTC):** 2026-07-16

## Policy

- No automatic mapping of all Zoho locations by name alone.
- Administrator confirmation required per location.
- Do not invent branches without operational intent.
- Defer non-operational / empty locations.

## Zoho locations (production snapshot)

| Location | Zoho ID | Invoices | Customers (via inv) | Prior mapping | Decision |
|----------|---------|----------|---------------------|---------------|----------|
| Nimruz | 303766000000093149 | 657 | 619 | Linked to branch `01` | **Keep** (P0) |
| Kabul | 303766000000093054 | 528 | 492 | none | **Import + link** local branch `KBL` |
| Kandahar | 303766000000132921 | 311 | 298 | none | **Import + link** local branch `KDR` |
| Ghazni | 303766000000132956 | 280 | 271 | none | **Import + link** local branch `GZN` |
| Buldak | 303766000000299061 | 136 | 128 | none | **Import + link** local branch `BLD` |
| Helmand | 303766000000132990 | 72 | 70 | none | **Import + link** local branch `HLM` |
| Headquarter | 303766000000172007 | 0 | 0 | none | **Defer / non-operational** |
| test | 303766000000396827 | 0 | 0 | none | **Ignore / non-operational** |

## Conflict handling

Customers with invoices in multiple Zoho locations → `multi_location_conflict` review UI (`/zoho/mapping-conflicts`). Not auto-assigned.

Customers with payments/assignments → operational_history_conflict; skip automatic branch moves.

## Apply order

1. Preview via `GET /zoho/location-mapping-review`
2. Apply decisions for Kabul → Kandahar → Ghazni → Buldak → Helmand
3. Reprocess invoices for each location, then customer inheritance
4. Leave Headquarter + test deferred
5. Report before/after counts

## After controlled apply (production 2026-07-16)

| Metric | Before P1 apply | After |
|--------|-----------------|-------|
| Mapped customers | 625 | **1888** |
| Unmapped customers | ~6282–5024 band | **5024** |
| Mapped invoices | 658 | **1990** |
| Unmapped invoices | 1331 | **5** |
| Branches linked | Nimruz only | + KABUL, KANDAHAR, GHAZNI, BULDAK, HELMAND |
| Headquarter / test | — | deferred / ignored |

One Kabul customer conflict skipped during reprocess (`conflicts=1`). Multi-location conflict queue remains available in UI.

STAGE3-TEST branches `S3A`/`S3B` remain test-only; not linked to Zoho locations.
