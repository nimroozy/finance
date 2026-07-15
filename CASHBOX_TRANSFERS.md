# Cashbox Transfers

API: `/api/v1/cashbox-transfers/*` supports draft → submit → approve → send → receive → reverse between branch cashboxes (including head-office / bank-clearing destinations when configured). Source balance is checked under row locks. Transfers are not editable after approval; reversals use compensating ledger entries.
