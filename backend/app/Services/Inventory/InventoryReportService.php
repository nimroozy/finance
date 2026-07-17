<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportService
{
    public function stockBalancesCsv(?int $branchId = null): StreamedResponse
    {
        $query = StockBalance::query()
            ->with(['product:id,code,name_en', 'location:id,code,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['product_code', 'product_name', 'location_code', 'on_hand', 'reserved', 'available', 'in_transit']);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->product?->code,
                        $row->product?->name_en,
                        $row->location?->code,
                        $row->on_hand,
                        $row->reserved,
                        $row->available(),
                        $row->in_transit,
                    ]);
                }
            });
            fclose($out);
        }, 'inventory-stock-balances.csv', ['Content-Type' => 'text/csv']);
    }

    public function transactionsCsv(?int $branchId = null): StreamedResponse
    {
        $query = StockTransaction::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['txn_number', 'type', 'product_id', 'qty', 'from_location_id', 'to_location_id', 'occurred_at']);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->txn_number,
                        $row->type,
                        $row->product_id,
                        $row->qty,
                        $row->from_location_id,
                        $row->to_location_id,
                        optional($row->occurred_at)->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        }, 'inventory-transactions.csv', ['Content-Type' => 'text/csv']);
    }
}
