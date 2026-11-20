<?php

final class Money
{
    public static function ghsToMinor($value): int
    {
        if (is_int($value)) $value = (string)$value;
        if (!is_string($value) || !preg_match('/\\A(0|[1-9][0-9]{0,10})(?:\\.([0-9]{1,2}))?\\z/', $value, $matches)) {
            throw new InvalidArgumentException('Enter a valid GHS amount with at most two decimal places.');
        }
        $whole = (int)$matches[1];
        $fraction = isset($matches[2]) ? (int)str_pad($matches[2], 2, '0') : 0;
        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) throw new InvalidArgumentException('The amount is too large.');
        return $whole * 100 + $fraction;
    }

    public static function commission(int $grossMinor, int $basisPoints): int
    {
        if ($grossMinor < 0 || $basisPoints < 0 || $basisPoints > 10000
            || ($basisPoints > 0 && $grossMinor > intdiv(PHP_INT_MAX - 5000, $basisPoints))) {
            throw new InvalidArgumentException('Invalid amount or commission rate.');
        }
        return intdiv($grossMinor * $basisPoints + 5000, 10000);
    }

    public static function formatGhs(int $minor): string
    {
        $absolute = abs($minor);
        return ($minor < 0 ? '-' : '') . 'GHS ' . number_format(intdiv($absolute, 100), 0, '.', ',')
            . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
