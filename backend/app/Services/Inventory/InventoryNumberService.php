<?php

namespace App\Services\Inventory;

use App\Models\Inventory\AssetSequence;
use App\Models\Inventory\EquipmentSequence;
use App\Models\Inventory\PoSequence;
use App\Models\Inventory\ReservationSequence;
use App\Models\Inventory\SaleSequence;
use App\Models\Inventory\TransferSequence;
use Illuminate\Database\Eloquent\Model;

class InventoryNumberService
{
    public function next(string $prefix, string $sequenceClass, int $branchId): string
    {
        /** @var class-string<Model> $sequenceClass */
        $year = (int) now()->format('Y');
        $seq = $sequenceClass::query()
            ->where('branch_id', $branchId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $seq) {
            $sequenceClass::query()->create([
                'branch_id' => $branchId,
                'year' => $year,
                'last_sequence' => 0,
            ]);
            $seq = $sequenceClass::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_sequence = (int) $seq->last_sequence + 1;
        $seq->save();

        return sprintf('%s-%d-%d-%06d', $prefix, $branchId, $year, $seq->last_sequence);
    }

    public function equipment(int $branchId): string
    {
        return $this->next('EQ', EquipmentSequence::class, $branchId);
    }

    public function transfer(int $branchId): string
    {
        return $this->next('TR', TransferSequence::class, $branchId);
    }

    public function reservation(int $branchId): string
    {
        return $this->next('RSV', ReservationSequence::class, $branchId);
    }

    public function po(int $branchId): string
    {
        return $this->next('PO', PoSequence::class, $branchId);
    }

    public function sale(int $branchId): string
    {
        return $this->next('ES', SaleSequence::class, $branchId);
    }

    public function asset(int $branchId): string
    {
        return $this->next('FA', AssetSequence::class, $branchId);
    }

    public function grn(int $branchId): string
    {
        return $this->next('GRN', PoSequence::class, $branchId);
    }

    public function count(int $branchId): string
    {
        return $this->next('CNT', AssetSequence::class, $branchId);
    }

    public function purchaseRequest(int $branchId): string
    {
        return $this->next('PR', PoSequence::class, $branchId);
    }
}
