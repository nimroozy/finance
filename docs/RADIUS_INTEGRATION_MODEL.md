# SAS Radius Integration Model (Stage 10)

## Context

Each branch may run an **independent SAS Radius** server for subscriber authentication and service control.

Radius remains the subscriber authentication and service platform until its functions are safely exposed through **integration adapters**.

## Architecture

```
Application (central)
  └─ Radius Integration domain
       ├─ Branch adapter config (URL/agent endpoint, credentials encrypted)
       ├─ Cached subscriber / session / package views (PostgreSQL)
       ├─ Outbound command queue (activate, suspend, …)
       └─ Inbound sync jobs (usage, online status, health)
            │
            ▼
     Per-branch SAS Radius (or local secure agent)
```

## Hard rules

1. **Do not** make normal page loads depend on live Radius DB/API availability.
2. UI reads **cached synchronized views**.
3. Mutations enqueue **commands**; workers execute against adapters with retry/backoff.
4. Branch isolation: adapter credentials and commands are per branch.
5. No passwords over WhatsApp; Radius credentials never exposed to frontend.

## Adapter capabilities (target)

- Customer / account lookup
- Radius account creation
- Activate / suspend
- Change package
- Expiration management
- Online status
- Sessions
- Usage
- Disconnect (CoA / POD as supported)
- Package mapping
- Customer mapping (local customer ↔ Radius account)
- Health checks

## Local agent (optional later)

If direct API/DB access is inadequate or unsafe across networks, deploy a **secure local agent** at the branch that:

- Authenticates to central API with mTLS or signed tokens
- Pulls commands / pushes sync snapshots
- Never opens Radius DB to the public internet

## Data model sketch

- `radius_branch_connections` — encrypted secrets, endpoint, health
- `radius_package_mappings` — local/Zoho package ↔ Radius profile
- `radius_customer_accounts` — mapping + last sync
- `radius_command_jobs` — queued commands + status + idempotency
- `radius_session_snapshots` / `radius_usage_snapshots` — cache tables

## Coupling

| Caller | Must |
|--------|------|
| Installations (Stage 8) | Dispatch `RadiusActivateRequested` |
| Tickets / NOC | Dispatch suspend/disconnect commands |
| Collections | May read online status from cache only |
| WhatsApp | Never talk to Radius directly |

## Stage 10 exit sketch

- Health dashboard per branch adapter
- Activate/suspend from installation/ticket flows via queue
- Cache freshness metrics
- No synchronous Radius calls on page render
