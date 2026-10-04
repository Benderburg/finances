<?php

namespace App\Console\Commands;

use App\Domain\ReferenceRates;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

final class SyncReferenceRates extends Command
{
    protected $signature = 'norocel:fx-sync {--from=} {--to=} {--force : Recheck cached published versions}';

    protected $description = 'Fetch BNM reference history without changing financial data';

    public function handle(ReferenceRates $fx): int
    {
        $from = $this->option('from') ?? now('Europe/Chisinau')->toDateString();
        $to = $this->option('to') ?? $from;
        Validator::make(compact('from', 'to'), ['from' => 'date_format:Y-m-d', 'to' => 'date_format:Y-m-d|after_or_equal:from'])->validate();
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            $this->error('Limit: 366 days per invocation.');

            return self::FAILURE;
        }
        $failed = false;
        for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $to; $d = $d->addDay()) {
            $result = $fx->sync($d->toDateString(), (bool) $this->option('force'));
            $bad = isset($result['error']);
            $failed = $failed || $bad;
            $this->line($d->toDateString().' '.($bad ? $result['error'] : 'OK'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
