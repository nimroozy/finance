<?php

namespace App\Services\Ownership;

use App\Models\Branch;
use App\Models\BranchPaymentConfiguration;
use App\Models\BranchZohoAccountMapping;
use App\Models\User;
use App\Models\ZohoAccount;
use App\Models\ZohoLocation;
use App\Models\ZohoPaymentMode;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BranchPaymentMappingService
{
    public function __construct(private AuditLogger $audit) {}

    public function upsert(
        Branch $branch,
        User $actor,
        string $zohoAccountId,
        ?string $zohoPaymentModeId = null,
        ?string $currency = null,
        ?string $receiptPrefix = null,
        ?string $zohoLocationId = null,
    ): BranchZohoAccountMapping {
        $account = ZohoAccount::query()->where('zoho_account_id', $zohoAccountId)->first();
        if (! $account) {
            throw new InvalidArgumentException('Select a synchronized Zoho account.');
        }

        $mode = null;
        if ($zohoPaymentModeId) {
            $mode = ZohoPaymentMode::query()->where('zoho_payment_mode_id', $zohoPaymentModeId)->first();
            if (! $mode) {
                throw new InvalidArgumentException('Select a synchronized Zoho payment mode.');
            }
        }

        $locationId = $zohoLocationId ?: $branch->zoho_location_id;
        if ($locationId && ! ZohoLocation::query()->where('zoho_location_id', $locationId)->exists()) {
            throw new InvalidArgumentException('Zoho location is not synchronized.');
        }

        return DB::transaction(function () use ($branch, $actor, $account, $mode, $currency, $receiptPrefix, $locationId) {
            $mapping = BranchZohoAccountMapping::query()->firstOrNew(['branch_id' => $branch->id]);
            $before = $mapping->exists ? $mapping->toArray() : null;
            $mapping->fill([
                'zoho_location_id' => $locationId,
                'zoho_account_id' => $account->zoho_account_id,
                'zoho_account_name' => $account->name,
                'zoho_payment_mode_id' => $mode?->zoho_payment_mode_id,
                'zoho_payment_mode_name' => $mode?->name,
                'currency' => strtoupper($currency ?: 'AFN'),
                'receipt_prefix' => $receiptPrefix ?: $branch->receipt_prefix,
                'is_active' => true,
                'mapping_version' => ((int) ($mapping->mapping_version ?? 0)) + 1,
                'updated_by' => $actor->id,
            ]);
            $mapping->save();

            BranchPaymentConfiguration::query()->updateOrCreate(
                ['branch_id' => $branch->id],
                ['account_mapping_id' => $mapping->id]
            );

            $this->validate($mapping);
            $this->audit->log('branch.account_mapped', $mapping, $before, $mapping->fresh()->toArray(), $branch->id);

            return $mapping->fresh();
        });
    }

    public function validate(BranchZohoAccountMapping $mapping): BranchZohoAccountMapping
    {
        $status = 'ready';
        $message = 'Mapping is ready for live Zoho payments.';

        if (! $mapping->zoho_location_id && ! $mapping->branch?->zoho_location_id) {
            $status = 'missing_location';
            $message = 'Payment cannot be posted because the Zoho location for this branch is not configured.';
        }

        $account = ZohoAccount::query()->where('zoho_account_id', $mapping->zoho_account_id)->first();
        if (! $account) {
            $status = 'missing_account';
            $message = 'Payment cannot be posted because the Zoho cash or bank account is not configured.';
        } elseif (! $account->is_active) {
            $status = 'inactive_account';
            $message = 'Payment cannot be posted because the mapped Zoho account is inactive.';
        }

        if ($status === 'ready' && ! $mapping->zoho_payment_mode_id && ! $mapping->zoho_payment_mode_name) {
            $status = 'missing_payment_mode';
            $message = 'Payment cannot be posted because the Zoho payment mode for this branch is not configured.';
        }

        if ($status === 'ready' && $account) {
            $accountCurrency = strtoupper((string) ($account->raw['currency_code'] ?? $mapping->currency ?? 'AFN'));
            if ($mapping->currency && $accountCurrency && $accountCurrency !== strtoupper($mapping->currency)
                && isset($account->raw['currency_code'])) {
                $status = 'currency_mismatch';
                $message = 'Payment cannot be posted because the Zoho account currency does not match the branch currency.';
            }
        }

        $dup = BranchZohoAccountMapping::query()
            ->where('zoho_account_id', $mapping->zoho_account_id)
            ->where('is_active', true)
            ->where('id', '!=', $mapping->id)
            ->where('branch_id', '!=', $mapping->branch_id)
            ->exists();
        // Same account on multiple branches is allowed for cash pools but flagged for review only when currencies differ — keep ready.

        $mapping->update([
            'readiness_status' => $status,
            'validation_status' => $status === 'ready' ? 'valid' : 'failed',
            'validation_message' => $message,
            'last_validated_at' => now(),
        ]);

        $this->audit->log('branch.account_validated', $mapping, null, [
            'readiness_status' => $status,
            'message' => $message,
        ], $mapping->branch_id);

        return $mapping->fresh();
    }

    public function readinessForBranch(int $branchId): array
    {
        $mapping = BranchZohoAccountMapping::query()->where('branch_id', $branchId)->first();
        if (! $mapping) {
            return [
                'status' => 'missing_account',
                'ready' => false,
                'message' => 'Payment cannot be posted because the Zoho cash or bank account for this branch is not configured.',
                'mapping' => null,
            ];
        }
        $mapping = $this->validate($mapping);

        return [
            'status' => $mapping->readiness_status,
            'ready' => $mapping->isReady(),
            'message' => $mapping->validation_message,
            'mapping' => $mapping,
        ];
    }

    public function assertReadyForLivePayment(int $branchId, bool $dryRunAllowed = false): BranchZohoAccountMapping
    {
        $config = BranchPaymentConfiguration::query()->where('branch_id', $branchId)->first();
        $readiness = $this->readinessForBranch($branchId);
        if ($readiness['ready'] && $readiness['mapping']) {
            return $readiness['mapping'];
        }
        if ($dryRunAllowed && ($config?->allow_dry_run_without_mapping ?? true)) {
            if ($readiness['mapping']) {
                return $readiness['mapping'];
            }
        }
        throw new InvalidArgumentException($readiness['message'] ?: 'Branch payment mapping is not ready.');
    }
}
