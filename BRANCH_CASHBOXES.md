# Branch Cashboxes

Each branch/currency has a main cashbox (`branch_cashboxes`) with an append-only ledger (`branch_cashbox_transactions`). Credits occur on approved collector handovers; debits on transfers/bank deposits/reversals.

Balance must remain recalculable from ledger rows.
