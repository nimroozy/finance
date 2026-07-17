<?php

namespace App\Services\Crm;

use App\Models\Crm\Lead;
use Illuminate\Support\Collection;

class DuplicateLeadDetector
{
    /**
     * @return Collection<int, Lead>
     */
    public function findByPhone(string $phone, int $branchId, ?int $excludeLeadId = null): Collection
    {
        $normalized = $this->normalize($phone);
        if ($normalized === '') {
            return collect();
        }

        return Lead::query()
            ->where('branch_id', $branchId)
            ->when($excludeLeadId, fn ($q) => $q->where('id', '!=', $excludeLeadId))
            ->where(function ($q) use ($phone, $normalized) {
                $q->where('phone', $phone)
                    ->orWhere('whatsapp', $phone)
                    ->orWhere('phone', $normalized)
                    ->orWhere('whatsapp', $normalized)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(phone,''), ' ', ''), '-', ''), '+', '') = ?", [$normalized])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(whatsapp,''), ' ', ''), '-', ''), '+', '') = ?", [$normalized]);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    public function normalize(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $phone) ?? '';
    }
}
