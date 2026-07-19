# Stage 10.5 — Known Issues

## 1. PHP `bcmath` extension missing in this sandbox (ENVIRONMENT — escalation required)

**Status:** blocked by organization egress policy, not by any code in this
branch. **This is an environment limitation to escalate, not a defect to
dismiss.**

- `php -m` in this runtime does **not** list `bcmath` (full list recorded in
  `STAGE_10_5_DELIVERY_REPORT.md`).
- `App\Support\Money::normalize()` (`backend/app/Support/Money.php:25`) calls
  `bcadd()`. Without the extension every endpoint that normalizes money —
  invoice balance decoration, payment preview/draft/confirm/reverse, cash
  handovers, wallet, reconciliation — returns HTTP 500 with
  `Call to undefined function App\Support\bcadd()`.
- Install was attempted once and refused by the proxy:
  `apt-get install php8.4-bcmath` → `403 Forbidden` from
  `ppa.launchpadcontent.net` (the agent proxy's README explicitly says not to
  retry organization-policy denials — so it was not retried). No offline
  `.deb`, `gmp`, or alternative bignum extension is available in the image.

**Proof it is pre-existing, not introduced here:**
`git diff <stage-10.4-baseline>..HEAD -- backend/app/Support/Money.php
backend/tests/Concerns/CreatesPaymentFixtures.php` is **empty** — the money
code and payment fixtures are byte-identical to Stage 10.4.

**Impact on validation:**
- Backend `php artisan test`: **255 passed, 46 failed** — all 46 failures are
  the identical `bcadd()` error (verified: 46 FAILED lines, 46 `bcadd`
  occurrences).
- `npm run e2e:acceptance`: the Stage 10.4 write-path workflow specs share a
  `fixtureIds()` helper that fetches `/api/v1/invoices` (bcmath) and so fail
  at setup; the Stage 10.5 UI-driven suite tolerates the gap and passes.

**Required action (outside this sandbox):** re-run
`php artisan test` and `npm run e2e:acceptance` on a runtime that has
`bcmath` (any standard PHP image: `docker-php-ext-install bcmath`, or
`apt-get install php8.4-bcmath` where egress is allowed). The financial
tests are expected to pass there — the failures here are solely the missing
extension.

## 2. GitHub push access unavailable in this session (ENVIRONMENT)

Pushing `claude/stage-10-5-professional-ui` returns 403 from both the git
proxy and the GitHub App integration. The branch is preserved as a git
bundle + patch series + full binary diff (see
`STAGE_10_5_DELIVERY_REPORT.md`) so the work is never trapped only inside the
remote environment.

## 3. Preview deployment not performed from this sandbox (ENVIRONMENT)

A non-production preview deployment (separate URL/DB/Redis, acceptance
fixtures, Zoho test adapter) was **not** performed because (a) production
deployment is explicitly out of scope, (b) no non-production hosting target
or credentials were provided to this environment, and (c) credentials must
be supplied securely outside Git. The full local acceptance stack used for
testing (Postgres `collection_acceptance`, Redis DB 2, Zoho test adapter,
WhatsApp/Radius disabled) is documented in `STAGE_10_5_UI_ACCEPTANCE.md` and
is directly reproducible as the preview recipe.

## 4. next-intl absolute-redirect origin under a reverse proxy (UPSTREAM, mitigated)

When a `next-intl` locale-prefix redirect (307) is produced by a raw browser
navigation behind a reverse proxy, the absolute `Location` is built from
Next's own `request.url` (the app server's bind address), not the public
origin. This only triggers on a pre-hydration hard navigation. Fixed in-app
by ensuring record cards navigate client-side (`MobileRecordCard` now uses
the locale-aware `Link`, not a raw `<a>`); tests also wait for hydration.
Not reproducible in a normal same-origin deployment.

## 5. Small secondary-control touch targets (MINOR, AA-compliant)

A few dense secondary controls (table pagination) render at ~36px. This
meets WCAG 2.1 **AA**; the 44px minimum is **AAA** (2.5.5). Primary
field-workflow actions are 44–48px. Tracked, not blocking.
