# Zoho Books setup (Stage 2)

## Required environment variables

Set these in `/opt/collection-system/.env` (never commit real values):

```bash
ZOHO_CLIENT_ID=
ZOHO_CLIENT_SECRET=
ZOHO_REDIRECT_URI=https://finance.mns.af/api/v1/zoho/oauth/callback
ZOHO_ORGANIZATION_ID=          # optional until selected in UI
ZOHO_DATA_CENTER=us            # us|eu|in|au|ca|sa
ZOHO_ACCOUNTS_DOMAIN=https://accounts.zoho.com
ZOHO_API_DOMAIN=https://www.zohoapis.com
```

After updating `.env`, recreate backend workers:

```bash
cd /opt/collection-system
docker compose up -d --force-recreate backend queue-worker scheduler
```

## Create a Zoho API client

1. In the Zoho API Console, create a **Server-based** application.
2. Authorized redirect URI must match `ZOHO_REDIRECT_URI` exactly.
3. Scopes typically include Zoho Books contacts/invoices read (adjust per your DC policy).
4. Choose the correct data center for your organization.

## Connect in the application

1. Sign in as Super Administrator.
2. Open **Zoho** (`/en/zoho`).
3. Click **Connect**, complete OAuth consent.
4. Select the organization if more than one is returned.
5. Configure **Branch mappings**.
6. Run **Manual sync** (customers, then invoices, or full).
7. Review **Sync jobs**, **API logs**, and **Unmapped customers**.

## Behavior notes

- Tokens are encrypted at rest.
- Access tokens refresh automatically when near expiry.
- Local PostgreSQL is the operational source; Zoho is synced in the background.
- Unmapped customers are hidden from branch users until an admin maps them.
- Sync is never marked successful without storing returned Zoho entity IDs.

## Permissions

| Permission | Who |
|------------|-----|
| `zoho.configure` | Super Administrator |
| `zoho.view` / `zoho.sync` | Super Admin + Central Finance (+ Auditor view) |
| Branch customer/invoice/debtor views | Branch Managers (scoped) |

Collectors do not receive Zoho or debtor lists in Stage 2 (assignments arrive in Stage 3).
