<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InventoryImportService
{
    public function __construct(
        private ProductService $products,
        private SiteService $sites,
        private TowerService $towers,
        private SupplierService $suppliers,
        private EquipmentService $equipment,
        private StockLedgerService $ledger,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{valid:int, errors:list<string>, preview:list<array<string, mixed>>}
     */
    public function dryRun(string $type, array $rows): array
    {
        $errors = [];
        $preview = [];
        foreach ($rows as $i => $row) {
            $line = $i + 1;
            try {
                $this->validateRow($type, $row);
                $preview[] = $row;
            } catch (\Throwable $e) {
                $errors[] = "Line {$line}: ".$e->getMessage();
            }
        }

        return ['valid' => count($preview), 'errors' => $errors, 'preview' => $preview];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created:int, errors:list<string>}
     */
    public function apply(string $type, array $rows, ?User $actor = null): array
    {
        $created = 0;
        $errors = [];

        try {
            DB::transaction(function () use ($type, $rows, $actor, &$created, &$errors) {
                foreach ($rows as $i => $row) {
                    try {
                        $this->applyRow($type, $row, $actor);
                        $created++;
                    } catch (\Throwable $e) {
                        $errors[] = 'Line '.($i + 1).': '.$e->getMessage();
                    }
                }
                if ($errors !== []) {
                    throw new RuntimeException('Import failed');
                }
            });
        } catch (RuntimeException) {
            // errors already collected
        }

        return ['created' => $errors === [] ? $created : 0, 'errors' => $errors];
    }

    /** @param array<string, mixed> $row */
    private function validateRow(string $type, array $row): void
    {
        match ($type) {
            'products' => empty($row['name_en']) ? throw new InvalidArgumentException('name_en required') : null,
            'opening_balances' => (empty($row['product_id']) || empty($row['location_id']) || ! isset($row['qty']) || empty($row['branch_id']))
                ? throw new InvalidArgumentException('product_id, location_id, qty, branch_id required') : null,
            'serials' => (empty($row['product_id']) || empty($row['serial_number']) || empty($row['location_id']) || empty($row['branch_id']))
                ? throw new InvalidArgumentException('product_id, serial_number, location_id, branch_id required') : null,
            'sites' => (empty($row['code']) || empty($row['name']) || empty($row['branch_id']))
                ? throw new InvalidArgumentException('code, name, branch_id required') : null,
            'towers' => (empty($row['code']) || empty($row['site_id']))
                ? throw new InvalidArgumentException('code, site_id required') : null,
            'suppliers' => empty($row['name']) ? throw new InvalidArgumentException('name required') : null,
            'assets' => empty($row['name']) ? throw new InvalidArgumentException('name required') : null,
            default => throw new InvalidArgumentException('Unknown import type'),
        };
    }

    /** @param array<string, mixed> $row */
    private function applyRow(string $type, array $row, ?User $actor): void
    {
        $this->validateRow($type, $row);
        match ($type) {
            'products' => $this->products->create($row, $actor),
            'opening_balances' => $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_RECEIPT,
                'branch_id' => $row['branch_id'],
                'product_id' => $row['product_id'],
                'qty' => $row['qty'],
                'unit_cost' => $row['unit_cost'] ?? null,
                'to_location_id' => $row['location_id'],
                'idempotency_key' => $row['idempotency_key'] ?? ('import-ob-'.Str::uuid()),
                'reason' => 'opening_balance',
            ], $actor),
            'serials' => $this->equipment->receiveSerial($row, $actor),
            'sites' => $this->sites->create($row, $actor),
            'towers' => $this->towers->create($row, $actor),
            'suppliers' => $this->suppliers->create($row, $actor),
            'assets' => null,
            default => null,
        };
    }
}
