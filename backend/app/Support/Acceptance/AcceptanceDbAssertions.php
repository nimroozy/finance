<?php

namespace App\Support\Acceptance;

use App\Models\AuditLog;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Services\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcceptanceDbAssertions
{
    /**
     * @param  array<string, mixed>  $expect
     * @return array{ok: bool, entity: string, record: ?array<string, mixed>, mismatches: list<string>, message: string}
     */
    public function assertEntity(string $entity, string $key, mixed $value, array $expect = []): array
    {
        $record = match ($entity) {
            'customer' => Customer::query()->withoutGlobalScopes()->where($key, $value)->first(),
            'lead' => Lead::query()->withoutGlobalScopes()->where($key, $value)->first(),
            'service' => Service::query()->withoutGlobalScopes()->where($key, $value)->first(),
            'user' => User::query()->where($key, $value)->first(),
            'audit' => AuditLog::query()->where($key, $value)->latest('id')->first(),
            default => null,
        };

        if (! $record) {
            return [
                'ok' => false,
                'entity' => $entity,
                'record' => null,
                'mismatches' => ["{$entity} not found where {$key}={$value}"],
                'message' => 'Record not found',
            ];
        }

        $mismatches = [];
        foreach ($expect as $field => $expected) {
            $actual = data_get($record, $field);
            if ((string) $actual !== (string) $expected) {
                $mismatches[] = "{$field}: expected {$expected}, got ".json_encode($actual);
            }
        }

        return [
            'ok' => $mismatches === [],
            'entity' => $entity,
            'record' => $record->toArray(),
            'mismatches' => $mismatches,
            'message' => $mismatches === [] ? 'ok' : 'Field mismatches',
        ];
    }

    /**
     * Immutable ledger identity check for acceptance DB.
     *
     * Formula (acceptance reset opening = 0):
     * receipts + transfer_receive + positive adjustments
     * - installation/sale/issue/custody/loss/scrap/transfer_dispatch
     * = sum(on_hand)
     *
     * @return array{ok: bool, opening: string, closing: string, computed_closing: string, formula: string, details: array<string, mixed>}
     */
    public function stockReconciliation(): array
    {
        if (! Schema::hasTable('inventory_stock_balances') || ! Schema::hasTable('inventory_stock_transactions')) {
            return [
                'ok' => true,
                'opening' => '0',
                'closing' => '0',
                'computed_closing' => '0',
                'formula' => 'skipped-no-tables',
                'details' => ['note' => 'inventory stock tables absent'],
            ];
        }

        $closingNorm = number_format((float) (DB::table('inventory_stock_balances')->sum('on_hand') ?? 0), 3, '.', '');

        $sumType = static fn (array $types): float => (float) (DB::table('inventory_stock_transactions')
            ->whereIn('type', $types)
            ->sum('qty') ?? 0);

        $receipts = $sumType(['receipt', 'return']);
        $inboundTransfers = $sumType(['transfer_receive']);
        $adjustments = (float) (DB::table('inventory_stock_transactions')->where('type', 'adjustment')->sum('qty') ?? 0);
        $adjIn = max(0.0, $adjustments);
        $adjOut = abs(min(0.0, $adjustments));
        $fulfilled = $sumType(['sale', 'installation', 'issue', 'custody']);
        $outboundTransfers = $sumType(['transfer_dispatch']);
        $writeOffs = $sumType(['loss', 'scrap', 'damage']);

        $computed = number_format(
            0.0 + $receipts + $inboundTransfers + $adjIn - $fulfilled - $outboundTransfers - $writeOffs - $adjOut,
            3,
            '.',
            ''
        );

        // Transfer immediate / repair move stock between locations without net change — excluded.
        $ok = $computed === $closingNorm;

        return [
            'ok' => $ok,
            'opening' => '0.000',
            'closing' => $closingNorm,
            'computed_closing' => $computed,
            'formula' => 'opening + receipts + inbound_transfers + adj_in - fulfilled - outbound_transfers - write_offs - adj_out = closing',
            'details' => [
                'receipts' => $receipts,
                'inbound_transfers' => $inboundTransfers,
                'adjustments_in' => $adjIn,
                'adjustments_out' => $adjOut,
                'reservations_fulfilled' => $fulfilled,
                'outbound_transfers' => $outboundTransfers,
                'write_offs' => $writeOffs,
                'transaction_count' => DB::table('inventory_stock_transactions')->count(),
            ],
        ];
    }
}
