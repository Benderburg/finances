<?php

namespace App\Migration;

use App\Domain\DomainError;

final class ExactJson
{
    public static function decode(string $json): array
    {
        // Preserve every decimal number lexeme before PHP's JSON decoder can turn it into a float.
        $out = '';
        $length = strlen($json);
        for ($i = 0; $i < $length;) {
            $ch = $json[$i];
            if ($ch === '"') {
                $start = $i++;
                while ($i < $length) {
                    if ($json[$i] === '\\') {
                        $i += 2;

                        continue;
                    } if ($json[$i++] === '"') {
                        break;
                    }
                } $out .= substr($json, $start, $i - $start);
            } elseif ($ch === '-' || ctype_digit($ch)) {
                if (! preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A', $json, $match, 0, $i)) {
                    throw new DomainError('INVALID_JSON');
                }
                $number = $match[0];
                $out .= strpbrk($number, '.eE') !== false ? json_encode($number) : $number;
                $i += strlen($number);
            } else {
                $out .= $ch;
                $i++;
            }
        }
        try {
            $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw new DomainError('INVALID_JSON');
        }
        if (! is_array($result) || array_is_list($result)) {
            throw new DomainError('INVALID_JSON');
        }

        return $result;
    }
}
