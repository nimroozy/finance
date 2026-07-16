<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchCustomerPrefix;
use Illuminate\Database\Seeder;

class BranchCustomerPrefixSeeder extends Seeder
{
    /**
     * Idempotent seed of initial customer-number prefixes.
     * Does not overwrite administrator-modified rows (same normalized_prefix already present).
     */
    public function run(): void
    {
        $map = [
            'KBL' => ['KABUL', 'KBL'],
            'NMZ' => ['NMZ', 'NIMRUZ', '01'],
            'GHZ' => ['GHAZNI', 'GHZ'],
            'BLD' => ['BULDAK', 'BLD', 'BOLDAK', 'BOLDAK'],
            'KDR' => ['KANDAHAR', 'KDR'],
            'HLD' => ['HELMAND', 'HLD'],
        ];

        $branches = Branch::withoutGlobalScopes()->get();
        $usedBranchIds = [];

        foreach ($map as $prefix => $codes) {
            if (BranchCustomerPrefix::query()->where('normalized_prefix', $prefix)->exists()) {
                continue;
            }

            $branch = $branches->first(function (Branch $b) use ($codes, $prefix, $usedBranchIds) {
                if (in_array($b->id, $usedBranchIds, true)) {
                    return false;
                }
                $code = strtoupper((string) $b->code);
                $name = strtoupper((string) ($b->name_en ?: $b->name_fa ?: $b->name ?? ''));
                $receipt = strtoupper((string) ($b->receipt_prefix ?? ''));

                foreach ($codes as $needle) {
                    $needle = strtoupper($needle);
                    if ($code === $needle || $receipt === $needle || $receipt === $prefix
                        || str_contains($name, $needle) || $code === $prefix) {
                        return true;
                    }
                }

                return false;
            });

            if (! $branch) {
                continue;
            }

            BranchCustomerPrefix::query()->create([
                'branch_id' => $branch->id,
                'prefix' => $prefix,
                'normalized_prefix' => $prefix,
                'active' => true,
                'priority' => 100,
                'description' => "Initial {$prefix} → {$branch->code} mapping",
            ]);
            $usedBranchIds[] = $branch->id;
        }
    }
}
