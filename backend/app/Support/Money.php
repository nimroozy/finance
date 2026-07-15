<?php

namespace App\Support;

/**
 * Safe string-based decimal arithmetic (bcmath) at scale 4.
 * Prefer this over float math for all payment / wallet amounts.
 */
final class Money
{
    public const SCALE = 4;

    public static function normalize(string|int|float $amount): string
    {
        if (is_float($amount)) {
            $amount = number_format($amount, self::SCALE, '.', '');
        }

        $value = (string) $amount;

        if (! is_numeric($value)) {
            return bcadd('0', '0', self::SCALE);
        }

        return bcadd($value, '0', self::SCALE);
    }

    public static function add(string|int|float $a, string|int|float $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function mul(string|int|float $a, string|int|float $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function div(string|int|float $a, string|int|float $b): string
    {
        $divisor = self::normalize($b);
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new \InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::normalize($a), $divisor, self::SCALE);
    }

    /**
     * @return int -1 if a < b, 0 if equal, 1 if a > b
     */
    public static function compare(string|int|float $a, string|int|float $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function isZero(string|int|float $amount): bool
    {
        return self::compare($amount, '0') === 0;
    }

    public static function isPositive(string|int|float $amount): bool
    {
        return self::compare($amount, '0') === 1;
    }

    public static function isNegative(string|int|float $amount): bool
    {
        return self::compare($amount, '0') === -1;
    }

    public static function equals(string|int|float $a, string|int|float $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    public static function min(string|int|float $a, string|int|float $b): string
    {
        return self::compare($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function max(string|int|float $a, string|int|float $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function sum(iterable $amounts): string
    {
        $total = self::normalize('0');
        foreach ($amounts as $amount) {
            $total = self::add($total, $amount);
        }

        return $total;
    }
}
