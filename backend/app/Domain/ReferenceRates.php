<?php

namespace App\Domain;

use App\Contracts\ReferenceRateProvider;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReferenceRates
{
    public function __construct(private readonly ReferenceRateProvider $provider) {}

    public function sync(string $date, bool $force = false): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('External FX fetch must be outside financial transactions.');
        }
        if (! $force && Cache::has('bnm:'.$date)) {
            return ['cached' => true] + Cache::get('bnm:'.$date);
        }
        // Cache errors briefly as well: provider outages must not cause a request storm.
        try {
            $rows = $this->provider->ratesOn($date);
            Cache::lock('bnm-write', 15)->block(5, function () use ($rows) {
                DB::transaction(function () use ($rows) {
                    foreach ($rows as $r) {
                        $latest = DB::table('fx_reference_rates')->where('provider', $r['provider'])->where('currency_code', $r['currency_code'])->where('effective_on', $r['effective_on'])->orderByDesc('version')->first();
                        if ($latest && BigDecimal::of($latest->mdl_per_unit)->isEqualTo($r['mdl_per_unit']) && $latest->published_nominal === $r['published_nominal'] && $latest->published_value === $r['published_value']) {
                            continue;
                        }
                        DB::table('fx_reference_rates')->insert(array_replace($r, ['id' => (string) Str::uuid(), 'version' => ($latest?->version ?? 0) + 1, 'fetched_at' => now(), 'provenance' => json_encode($r['provenance'], JSON_THROW_ON_ERROR)]));
                    }
                });
            });
            Cache::put('bnm:'.$date, ['available' => true], now()->addHours(6));

            return ['cached' => false, 'available' => true];
        } catch (\Throwable $e) {
            Cache::put('bnm:'.$date, ['available' => false, 'error' => 'RATE_PROVIDER_UNAVAILABLE'], now()->addMinutes(2));

            return ['available' => false, 'error' => 'RATE_PROVIDER_UNAVAILABLE'];
        }
    }

    public function rate(string $currency, string $date): ?array
    {
        if ($currency === 'MDL') {
            return ['id' => 'MDL-fixed', 'currency_code' => 'MDL', 'effective_on' => $date, 'mdl_per_unit' => '1', 'version' => 1, 'fallback' => false, 'provider' => 'BNM'];
        }
        $r = DB::table('fx_reference_rates')->where('provider', 'BNM')->where('currency_code', $currency)->whereBetween('effective_on', [CarbonImmutable::parse($date)->subDays(7)->toDateString(), $date])->orderByDesc('effective_on')->orderByDesc('version')->first();
        if (! $r) {
            return null;
        }
        $row = (array) $r;
        $row['provenance'] = json_decode($row['provenance'], true);

        return $row + ['fallback' => $row['effective_on'] !== $date];
    }

    public function convert(string $minor, string $source, string $target, string $date): array
    {
        if ($source === $target || BigDecimal::of($minor)->isZero()) {
            return ['amount_minor' => $minor, 'rates' => [], 'missing' => []];
        }
        $s = $this->rate($source, $date);
        $t = $this->rate($target, $date);
        if (! $s || ! $t) {
            return ['amount_minor' => null, 'rates' => [], 'missing' => [['source' => $source, 'target' => $target, 'requested_on' => $date]]];
        }
        // All four currencies have scale 2. Round each row exactly once, then sum.
        $amount = (string) BigDecimal::of($minor)->multipliedBy($s['mdl_per_unit'])->dividedBy($t['mdl_per_unit'], 0, RoundingMode::HALF_UP);

        return ['amount_minor' => $amount, 'missing' => [], 'rates' => array_map(fn ($r) => array_intersect_key($r, array_flip(['id', 'provider', 'currency_code', 'effective_on', 'version', 'fallback'])) + ['requested_on' => $date], [$s, $t])];
    }

    /** Rows carry a bucket, original money, valuation date, and optional context. */
    public function aggregate(iterable $rows, string $target, array $context = []): array
    {
        $totals = [];
        $rates = [];
        $missing = [];
        $unconverted = [];
        foreach ($rows as $row) {
            $bucket = $row['bucket'] ?? 'amount_minor';
            $totals[$bucket] ??= '0';
            $c = $this->convert($row['amount_minor'], $row['currency_code'], $target, $row['date']);
            if ($c['amount_minor'] === null) {
                $unconverted[] = $row;
                foreach ($c['missing'] as $m) {
                    $missing[json_encode($m)] = $m;
                }
            } else {
                $totals[$bucket] = Money::sum([$totals[$bucket], $c['amount_minor']]);
            }
            foreach ($c['rates'] as $r) {
                $rates[json_encode($r)] = $r;
            }
        }
        ksort($rates);
        $metadata = ['rates' => array_values($rates), 'context' => $context, 'display_currency' => $target, 'known_subtotal' => $totals, 'missing' => array_values($missing), 'unconverted' => $unconverted];

        return ['currency_code' => $target, 'known_subtotal' => $totals, 'incomplete' => (bool) $missing, 'missing' => array_values($missing), 'unconverted' => $unconverted, 'meta' => $metadata + ['cache_key' => hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR))]];
    }
}
