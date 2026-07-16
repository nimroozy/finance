# Custody-Aware Reversals

If a Stage 4 payment has `handover_status=handed_over`, simple Stage 4 reversal is blocked. `CustodyAwareReversalService` opens a `custody_conflicts` review so branch/central finance can debit the cashbox and create compensating wallet entries without rewriting the original handover.
