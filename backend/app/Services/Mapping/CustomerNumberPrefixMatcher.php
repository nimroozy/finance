<?php

namespace App\Services\Mapping;

use App\Models\BranchCustomerPrefix;

class CustomerNumberPrefixMatcher
{
    /**
     * Normalize a customer number for prefix matching.
     */
    public function normalizeCustomerNumber(?string $customerNumber): string
    {
        if ($customerNumber === null) {
            return '';
        }

        $value = trim($customerNumber);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return strtoupper($value);
    }

    /**
     * Detect the active configured prefix at the start of a customer number.
     *
     * @return array{prefix:string,branch_id:int,prefix_id:int}|null
     */
    public function detect(?string $customerNumber): ?array
    {
        $normalized = $this->normalizeCustomerNumber($customerNumber);
        if ($normalized === '') {
            return null;
        }

        $prefixes = BranchCustomerPrefix::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $matches = [];
        foreach ($prefixes as $row) {
            if ($this->startsWithPrefix($normalized, $row->normalized_prefix)) {
                $matches[] = $row;
            }
        }

        if ($matches === []) {
            return null;
        }

        // Duplicate active prefixes matching the same number → ambiguous.
        $branchIds = collect($matches)->pluck('branch_id')->unique()->values();
        if ($branchIds->count() > 1) {
            return [
                'prefix' => $matches[0]->normalized_prefix,
                'branch_id' => 0,
                'prefix_id' => 0,
                'ambiguous' => true,
                'matches' => $matches->map(fn ($m) => [
                    'prefix_id' => $m->id,
                    'branch_id' => $m->branch_id,
                    'prefix' => $m->normalized_prefix,
                ])->all(),
            ];
        }

        $best = $matches[0];

        return [
            'prefix' => $best->normalized_prefix,
            'branch_id' => (int) $best->branch_id,
            'prefix_id' => (int) $best->id,
            'ambiguous' => false,
        ];
    }

    public function startsWithPrefix(string $normalizedCustomerNumber, string $normalizedPrefix): bool
    {
        $prefix = strtoupper(trim($normalizedPrefix));
        if ($prefix === '' || $normalizedCustomerNumber === '') {
            return false;
        }

        // Prefix must be at the beginning. Allow optional separators then digits/rest.
        // KBL-001, KBL001, KBL 001, KBL_001, KBL/001
        $pattern = '/^'.preg_quote($prefix, '/').'(?:[\s\-_\/]+|(?=\d)|$)/u';

        return (bool) preg_match($pattern, $normalizedCustomerNumber);
    }

    public function hasUnknownAlphaPrefix(?string $customerNumber): bool
    {
        $normalized = $this->normalizeCustomerNumber($customerNumber);
        if ($normalized === '' || preg_match('/^\d/', $normalized)) {
            return false;
        }
        if ($this->detect($customerNumber) !== null) {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{2,}/', $normalized);
    }

    /**
     * Prefer explicit customer_number; fall back to a leading PREFIX-digits token in contact_name.
     * Business data often stores the customer number inside the Zoho contact name.
     */
    public function effectiveCustomerNumber(?string $customerNumber, ?string $contactName = null): ?string
    {
        if (filled($customerNumber)) {
            return trim((string) $customerNumber);
        }

        $name = trim((string) $contactName);
        if ($name === '') {
            return null;
        }

        if (preg_match('/^([A-Za-z]{2,}[\s\-_\/]*\d[\w]*)/u', $name, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
