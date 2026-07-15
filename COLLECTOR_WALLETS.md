# Collector wallets

Cash in hand tracked per collector + branch + currency. Only payment methods with `affects_cash_wallet` (seeded: `cash`) credit the wallet on confirm.

## Model

- `CollectorWallet` — balances: `balance`, `pending_handover_balance` (handover drawdown is Stage 5).
- `CollectorWalletTransaction` — **immutable ledger** (no update/delete after insert; comment on model).

## Transaction types

| Constant | Value | When |
|----------|-------|------|
| `TYPE_PAYMENT_CREDIT` | `payment_credit` | Cash payment confirmed |
| `TYPE_REVERSAL_DEBIT` | `reversal_debit` | Payment reversal approved (negative amount) |
| `TYPE_ADJUSTMENT` | `adjustment` | Reserved / rare adjustments |

One credit per payment enforced (`Wallet already credited for this payment`).

## Confirm / reverse

On cash confirm (`PaymentService`): if `payments.wallet.enabled` → `creditFromPayment` (balance + pending_handover_balance).

On reversal approve: `debitFromReversal` if method affects cash wallet; insufficient balance fails the reversal.

## API

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/collector/wallet` | `wallets.view`. Query: `collector_id`, `branch_id`, `currency` (default AFN). Collectors forced to own profile. |
| GET | `/api/v1/collector/wallet/transactions` | Paginated ledger for collector (optional `branch_id`) |

No Stage 4 endpoints to manually adjust balance or settle handovers.

## Config

`PAYMENT_WALLET_ENABLED` (default true). Toggle also via `/payment-settings` → `payment_wallet_enabled`.
