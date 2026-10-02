<?php

namespace App\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

final class Money
{
    public const MAX = '999999999999999';

    public const CURRENCIES = ['MDL', 'EUR', 'USD'];

    public static function minor(mixed $value, bool $zero = false): string
    {
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,14})$/D', $value) || BigInteger::of($value)->isGreaterThan(self::MAX) || (! $zero && $value === '0')) {
            throw new DomainError('INVALID_MONEY');
        }

        return $value;
    }

    public static function decimal(string $value): string
    {
        try {
            return self::minor((string) BigDecimal::of($value)->multipliedBy(100)->toScale(0, RoundingMode::Unnecessary)->toBigInteger(), true);
        } catch (\Throwable $e) {
            throw new DomainError('INVALID_MONEY');
        }
    }

    public static function rate(string $value): string
    {
        try {
            $rate = BigDecimal::of($value)->toScale(12, RoundingMode::Unnecessary);
        } catch (\Throwable) {
            throw new DomainError('FX_RATE_OUT_OF_RANGE');
        }
        if ($rate->isLessThanOrEqualTo(0) || $rate->isGreaterThan('999999999999999999.999999999999')) {
            throw new DomainError('FX_RATE_OUT_OF_RANGE');
        }

        return (string) $rate;
    }

    public static function target(string $source, string $rate): string
    {
        return self::minor((string) BigDecimal::of($source)->dividedBy(self::rate($rate), 0, RoundingMode::HalfUp)->toBigInteger());
    }

    public static function effective(string $source, string $target): string
    {
        return self::rate((string) BigDecimal::of($source)->dividedBy($target, 12, RoundingMode::HalfUp));
    }

    public static function sum(iterable $values): string
    {
        $sum = BigInteger::zero();
        foreach ($values as $value) {
            $sum = $sum->plus($value);
        }

        return (string) $sum;
    }

    public static function percent(string $part, string $total): string
    {
        if ($total === '0') {
            return '0';
        }

        return (string) BigDecimal::of($part)->multipliedBy(100)->dividedBy($total, 2, RoundingMode::HalfUp);
    }
}
