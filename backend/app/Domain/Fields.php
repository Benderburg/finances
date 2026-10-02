<?php

namespace App\Domain;

use Illuminate\Support\Facades\Validator;

final class Fields
{
    public static function check(array $input, array $rules): array
    {
        $unknown = array_diff(array_keys($input), array_keys($rules));
        if ($unknown) {
            throw new DomainError('UNKNOWN_FIELDS', 422, [], array_fill_keys($unknown, ['Not allowed']));
        }

        return Validator::make($input, $rules)->validate();
    }

    public static function revision(object|array $row, array $payload): void
    {
        $revision = is_array($row) ? $row['revision'] : $row->revision;
        if (! isset($payload['expected_revision']) || (string) $payload['expected_revision'] !== (string) $revision) {
            throw new DomainError('STALE_REVISION', 409);
        }
    }

    public static function currency(mixed $value): string
    {
        if (! in_array($value, Money::CURRENCIES, true)) {
            throw new DomainError('INVALID_CURRENCY');
        }

        return $value;
    }
}
