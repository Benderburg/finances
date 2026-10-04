<?php

namespace App\Domain;

use App\Contracts\ReferenceRateProvider;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

final class BnmRateProvider implements ReferenceRateProvider
{
    public const URL = 'https://www.bnm.md/ro/official_exchange_rates';

    public function ratesOn(string $date): array
    {
        $response = Http::connectTimeout(2)->timeout(5)->withOptions(['allow_redirects' => false])->get(self::URL, [
            'date' => CarbonImmutable::parse($date)->format('d.m.Y'), 'get_xml' => 1,
        ])->throw();

        return $this->parse($response->body(), $date);
    }

    public function parse(string $xml, string $requested): array
    {
        if (strlen($xml) > 1048576 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new DomainError('INVALID_RATE_RESPONSE');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            if (! $root || $root->getName() !== 'ValCurs') {
                throw new DomainError('INVALID_RATE_RESPONSE');
            }
            $published = (string) $root['Date'];
            $day = CarbonImmutable::createFromFormat('!d.m.Y', $published);
            if (! $day || $day->format('d.m.Y') !== $published || $day->toDateString() > $requested || $day->diffInDays(CarbonImmutable::parse($requested)) > 7) {
                throw new DomainError('INVALID_RATE_DATE');
            }
            $result = [];
            foreach ($root->Valute as $v) {
                $code = (string) $v->CharCode;
                if (! in_array($code, Money::CURRENCIES, true) || $code === 'MDL') {
                    continue;
                }
                $nominal = (string) $v->Nominal;
                $value = (string) $v->Value;
                if (isset($result[$code]) || ! preg_match('/^[1-9][0-9]{0,8}$/D', $nominal) || ! preg_match('/^[0-9]{1,12}(\.[0-9]{1,12})?$/D', $value) || ! BigDecimal::of($value)->isPositive()) {
                    throw new DomainError('INVALID_RATE_RESPONSE');
                }
                $result[$code] = ['currency_code' => $code, 'effective_on' => $day->toDateString(), 'mdl_per_unit' => (string) BigDecimal::of($value)->dividedBy($nominal, 18, RoundingMode::UNNECESSARY), 'published_nominal' => $nominal, 'published_value' => $value];
            }
            foreach (array_diff(Money::CURRENCIES, ['MDL']) as $code) {
                if (! isset($result[$code])) {
                    throw new DomainError('INCOMPLETE_RATE_RESPONSE');
                }
            }
            $result['MDL'] = ['currency_code' => 'MDL', 'effective_on' => $day->toDateString(), 'mdl_per_unit' => '1.000000000000000000', 'published_nominal' => '1', 'published_value' => '1'];
            $provenance = ['url' => self::URL, 'requested_on' => $requested, 'published_date' => $published, 'sha256' => hash('sha256', $xml)];

            return array_map(fn ($r) => $r + ['provider' => 'BNM', 'provenance' => $provenance], array_values($result));
        } catch (DomainError $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DomainError('INVALID_RATE_RESPONSE');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
