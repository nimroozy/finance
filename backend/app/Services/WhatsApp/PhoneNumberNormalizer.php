<?php

namespace App\Services\WhatsApp;

class PhoneNumberNormalizer
{
    public function normalize(?string $phone, ?string $defaultCountry = null): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if ($hasPlus) {
            return $this->validateE164($digits);
        }

        // A country code explicitly supplied without "+" must not be replaced.
        if (str_starts_with($digits, '93') && strlen($digits) === 11) {
            return $this->validateAfghanistan($digits);
        }

        $country = strtoupper($defaultCountry ?? (string) config('whatsapp.default_country', 'AF'));
        if ($country !== 'AF') {
            return null;
        }

        // Local AF mobiles: 07xxxxxxxx → 7xxxxxxxx (preserve carrier prefix 70–79).
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return $this->validateAfghanistan('93'.$digits);
        }

        return null;
    }

    public function tryNormalize(?string $phone, ?string $defaultCountry = null): ?string
    {
        return $this->normalize($phone, $defaultCountry);
    }

    private function validateAfghanistan(string $digits): ?string
    {
        if (! preg_match('/^937\d{8}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }

    private function validateE164(string $digits): ?string
    {
        if (! preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return null;
        }

        if (str_starts_with($digits, '93')) {
            return $this->validateAfghanistan($digits);
        }

        return '+'.$digits;
    }
}
