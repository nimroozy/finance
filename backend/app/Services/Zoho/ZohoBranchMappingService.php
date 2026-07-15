<?php

namespace App\Services\Zoho;

use App\Models\ZohoBranchMapping;
use App\Models\ZohoReportingTagMapping;

class ZohoBranchMappingService
{
    /**
     * Resolve local branch_id from a Zoho contact or invoice payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveBranchId(array $payload): ?int
    {
        $mappings = ZohoBranchMapping::query()
            ->where('is_active', true)
            ->get();

        foreach ($mappings as $mapping) {
            if ($mapping->mapping_method === ZohoBranchMapping::METHOD_REPORTING_TAG) {
                if ($this->matchReportingTag($payload, $mapping->zoho_value)) {
                    return (int) $mapping->branch_id;
                }

                continue;
            }

            if ($mapping->mapping_method === ZohoBranchMapping::METHOD_CUSTOM_FIELD) {
                $apiName = $mapping->zoho_label ?: $mapping->zoho_value;
                $value = $this->customFieldValue($payload, $apiName);

                if ($mapping->zoho_label && $value !== null && (string) $value === (string) $mapping->zoho_value) {
                    return (int) $mapping->branch_id;
                }

                // If only zoho_value is set, treat it as api_name whose value equals itself.
                if (! $mapping->zoho_label && $value !== null && (string) $value === (string) $mapping->zoho_value) {
                    return (int) $mapping->branch_id;
                }

                continue;
            }

            $value = $this->extractValue($payload, $mapping->mapping_method, $mapping->zoho_value);

            if ($value !== null && (string) $value === (string) $mapping->zoho_value) {
                return (int) $mapping->branch_id;
            }
        }

        // Dedicated reporting tag mapping table
        $tags = $payload['reporting_tags'] ?? $payload['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tagId = (string) ($tag['tag_id'] ?? $tag['report_tag_id'] ?? '');
                $optionId = (string) ($tag['tag_option_id'] ?? $tag['report_tag_option_id'] ?? '');

                if ($tagId === '' || $optionId === '') {
                    continue;
                }

                $row = ZohoReportingTagMapping::query()
                    ->where('tag_id', $tagId)
                    ->where('tag_option_id', $optionId)
                    ->whereNotNull('branch_id')
                    ->first();

                if ($row) {
                    return (int) $row->branch_id;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractValue(array $payload, string $method, string $expected): mixed
    {
        return match ($method) {
            ZohoBranchMapping::METHOD_ZOHO_BRANCH => $payload['branch_id']
                ?? $payload['branch_name']
                ?? null,
            ZohoBranchMapping::METHOD_ZOHO_LOCATION => $payload['location_id']
                ?? $payload['place_of_supply']
                ?? null,
            ZohoBranchMapping::METHOD_CUSTOM_FIELD => $this->customFieldValue($payload, $expected),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function customFieldValue(array $payload, string $apiName): mixed
    {
        $fields = $payload['custom_fields'] ?? [];

        if (! is_array($fields)) {
            return null;
        }

        foreach ($fields as $field) {
            $name = $field['api_name'] ?? $field['label'] ?? null;
            if ((string) $name === $apiName) {
                return $field['value'] ?? $field['value_formatted'] ?? null;
            }
        }

        // Direct key on payload (tests / simplified fixtures)
        return $payload[$apiName] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function matchReportingTag(array $payload, string $zohoValue): bool
    {
        $tags = $payload['reporting_tags'] ?? $payload['tags'] ?? [];

        if (! is_array($tags)) {
            return false;
        }

        foreach ($tags as $tag) {
            $candidates = [
                $tag['tag_option_id'] ?? null,
                $tag['report_tag_option_id'] ?? null,
                $tag['tag_option_name'] ?? null,
                $tag['option_name'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if ($candidate !== null && (string) $candidate === $zohoValue) {
                    return true;
                }
            }
        }

        return false;
    }
}
