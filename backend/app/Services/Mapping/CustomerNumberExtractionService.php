<?php

namespace App\Services\Mapping;

use App\Models\BranchCustomerPrefix;
use App\Models\Customer;

class CustomerNumberExtractionService
{
    public const SOURCE_ZOHO = 'zoho_customer_number';

    public const SOURCE_CONTACT_NAME = 'contact_name_prefix';

    public const SOURCE_ADMIN = 'administrator_override';

    public const SOURCE_LOCAL_IMPORT = 'local_import';

    public const SOURCE_UNKNOWN = 'unknown';

    public function __construct(
        private CustomerNumberPrefixMatcher $matcher,
    ) {}

    /**
     * Extract a configured-prefix customer number from customer_number and/or contact_name.
     *
     * @return array{
     *   extracted_customer_number:?string,
     *   clean_display_name:?string,
     *   source:string,
     *   confidence:string,
     *   prefix:?string,
     *   branch_id:?int,
     *   raw_token:?string,
     *   valid:bool,
     *   result_class:string,
     *   explanation:string
     * }
     */
    public function extract(Customer|array $customer): array
    {
        $number = is_array($customer) ? ($customer['customer_number'] ?? null) : $customer->customer_number;
        $name = is_array($customer) ? ($customer['contact_name'] ?? null) : $customer->contact_name;
        $number = filled($number) ? trim((string) $number) : null;
        $name = filled($name) ? trim((string) $name) : null;

        if ($number !== null) {
            $fromNumber = $this->parseConfiguredToken($number);
            if ($fromNumber['valid']) {
                return array_merge($fromNumber, [
                    'clean_display_name' => $this->displayNameWithoutLeadingNumber($name, $fromNumber['extracted_customer_number']),
                    'source' => self::SOURCE_ZOHO,
                    'confidence' => 'high',
                    'result_class' => 'extracted',
                    'explanation' => 'Verified from existing customer_number using configured prefix.',
                ]);
            }

            if ($this->matcher->hasUnknownAlphaPrefix($number)) {
                return $this->invalid('unknown_prefix', 'Customer number has an alphabetic prefix that is not configured.', $number, $name);
            }

            return $this->invalid('invalid_format', 'Existing customer_number is present but does not match a configured prefix pattern.', $number, $name);
        }

        if ($name === null || $name === '') {
            return $this->invalid('no_number', 'No customer number and no contact name available.', null, null);
        }

        // Reject phone-like leading tokens.
        if (preg_match('/^(\+?\d[\d\s\-()]{6,})/u', $name)) {
            return $this->invalid('invalid_format', 'Contact name starts with a phone-like value; not treated as a customer number.', null, $name);
        }

        $fromName = $this->parseConfiguredTokenFromName($name);
        if (! $fromName['valid']) {
            return $fromName;
        }

        return array_merge($fromName, [
            'source' => self::SOURCE_CONTACT_NAME,
            'confidence' => 'medium',
            'result_class' => 'extracted',
            'explanation' => 'Extracted configured prefix number from the start of contact_name.',
        ]);
    }

    /**
     * Canonical PREFIX-NUMBER with leading zeros preserved in the numeric segment.
     */
    public function toCanonical(string $prefix, string $numericPart): string
    {
        $prefix = strtoupper(trim($prefix));
        $numeric = preg_replace('/[^\d]/', '', $numericPart) ?? '';

        return $prefix.'-'.$numeric;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseConfiguredToken(string $token): array
    {
        $normalized = $this->matcher->normalizeCustomerNumber($token);
        $hit = $this->matcher->detect($normalized);
        if (! $hit || ! empty($hit['ambiguous']) || empty($hit['branch_id'])) {
            if ($hit && ! empty($hit['ambiguous'])) {
                return $this->invalid('conflict', 'Multiple active prefix configurations match this token.', $token, null);
            }

            return $this->invalid(
                $this->matcher->hasUnknownAlphaPrefix($token) ? 'unknown_prefix' : 'invalid_format',
                'Token does not start with a configured branch prefix.',
                $token,
                null
            );
        }

        $prefix = $hit['prefix'];
        if (! preg_match('/^'.preg_quote($prefix, '/').'[\s\-_\/]*(\d+)/u', $normalized, $m)) {
            return $this->invalid('invalid_format', 'Configured prefix found but numeric segment missing.', $token, null);
        }

        $canonical = $this->toCanonical($prefix, $m[1]);

        return [
            'extracted_customer_number' => $canonical,
            'clean_display_name' => null,
            'prefix' => $prefix,
            'branch_id' => (int) $hit['branch_id'],
            'raw_token' => $token,
            'valid' => true,
            'source' => self::SOURCE_UNKNOWN,
            'confidence' => 'medium',
            'result_class' => 'extracted',
            'explanation' => 'Parsed configured prefix token.',
        ];
    }

    /**
     * Only accept PREFIX at the beginning of the contact name.
     *
     * @return array<string, mixed>
     */
    protected function parseConfiguredTokenFromName(string $name): array
    {
        $prefixes = BranchCustomerPrefix::query()->active()->orderBy('priority')->orderBy('id')->get();
        $normalizedName = $this->matcher->normalizeCustomerNumber($name);

        foreach ($prefixes as $row) {
            $prefix = $row->normalized_prefix;
            // PREFIX then optional separators then digits at start only.
            $pattern = '/^'.preg_quote($prefix, '/').'(?:[\s\-_\/]+|(?=\d))(\d+)/u';
            if (! preg_match($pattern, $normalizedName, $m)) {
                continue;
            }

            $canonical = $this->toCanonical($prefix, $m[1]);
            $clean = $this->displayNameWithoutLeadingNumber($name, $canonical);

            return [
                'extracted_customer_number' => $canonical,
                'clean_display_name' => $clean,
                'prefix' => $prefix,
                'branch_id' => (int) $row->branch_id,
                'raw_token' => $m[0],
                'valid' => true,
                'source' => self::SOURCE_CONTACT_NAME,
                'confidence' => 'medium',
                'result_class' => 'extracted',
                'explanation' => 'Extracted from contact name start using configured prefix.',
            ];
        }

        if (preg_match('/^[A-Z]{2,}[\s\-_\/]*\d+/u', $normalizedName)) {
            return $this->invalid('unknown_prefix', 'Contact name starts with an alphabetic prefix that is not configured.', null, $name);
        }

        if (preg_match('/^[A-Z]{2,}/u', $normalizedName) && ! preg_match('/^\d/u', $normalizedName)) {
            return $this->invalid('malformed_contact_name', 'Contact name has a leading alpha token without a valid configured number pattern.', null, $name);
        }

        return $this->invalid('no_number', 'No configured customer number pattern at the beginning of the contact name.', null, $name);
    }

    protected function displayNameWithoutLeadingNumber(?string $name, ?string $canonicalNumber): ?string
    {
        if ($name === null || $name === '' || $canonicalNumber === null) {
            return $name;
        }

        $prefix = strtoupper(explode('-', $canonicalNumber)[0] ?? '');
        $digits = explode('-', $canonicalNumber)[1] ?? '';
        if ($prefix === '' || $digits === '') {
            return $name;
        }

        $pattern = '/^'.preg_quote($prefix, '/').'[\s\-_\/]*'.preg_quote($digits, '/').'\s*/iu';
        $clean = trim(preg_replace($pattern, '', $name) ?? $name);

        return $clean !== '' ? $clean : $name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function invalid(string $class, string $explanation, ?string $raw, ?string $name): array
    {
        return [
            'extracted_customer_number' => null,
            'clean_display_name' => $name,
            'source' => self::SOURCE_UNKNOWN,
            'confidence' => 'none',
            'prefix' => null,
            'branch_id' => null,
            'raw_token' => $raw,
            'valid' => false,
            'result_class' => $class,
            'explanation' => $explanation,
        ];
    }
}
