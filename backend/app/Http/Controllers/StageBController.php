<?php

namespace App\Http\Controllers;

use App\Domain\CommandBus;
use App\Domain\CsvService;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\Money;
use App\Domain\ReferenceRates;
use Illuminate\Http\Request;

final class StageBController extends Controller
{
    public function reference(Request $r, ReferenceRates $fx)
    {
        $r->validate(['date' => 'required|date_format:Y-m-d', 'refresh' => 'sometimes|boolean']);
        $date = $r->query('date');
        $sync = $r->boolean('refresh') ? $fx->sync($date) : ['cached' => true];

        return ['data' => ['requested_on' => $date, 'rates' => array_map(fn ($c) => ['currency_code' => $c, 'rate' => $fx->rate($c, $date)], Money::CURRENCIES), 'fetch' => $sync]];
    }

    public function quote(Request $r, ReferenceRates $fx)
    {
        $p = Fields::check($r->all(), ['amount_minor' => 'required|string', 'currency_code' => 'required|in:MDL,EUR,USD,RON', 'target_currency_code' => 'required|in:MDL,EUR,USD,RON', 'date' => 'required|date_format:Y-m-d']);
        Money::minor($p['amount_minor']);
        $result = $fx->convert($p['amount_minor'], $p['currency_code'], $p['target_currency_code'], $p['date']);

        return ['data' => $result + ['indicative' => true, 'incomplete' => $result['amount_minor'] === null]];
    }

    public function export(Request $r, CommandBus $bus, CsvService $csv)
    {
        $r->validate(['safe' => 'sometimes|boolean']);
        $contents = $bus->snapshot($r->user()->id, fn () => $csv->export($r->user()->id, $r->boolean('safe', true)))['data'];

        return response($contents, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="norocel-journal.csv"']);
    }

    public function preview(Request $r, CsvService $csv)
    {
        $r->validate(['file' => 'required|file|max:5120', 'options' => 'required|string|json|max:20000']);
        $options = json_decode($r->input('options'), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($options)) {
            throw new DomainError('CSV_INVALID_ROW');
        }

        return ['data' => $csv->preview($r->user()->id, file_get_contents($r->file('file')->getRealPath()), $options)];
    }

    public function apply(Request $r, CommandBus $bus, CsvService $csv)
    {
        $p = Fields::check($r->all(), ['preview_id' => 'required|uuid', 'selected_rows' => 'required|array|min:1|max:10000', 'selected_rows.*' => 'required|integer|min:1', 'accept_possible_duplicates' => 'required|boolean']);

        return $bus->execute($r->user()->id, $r->header('Idempotency-Key', ''), 'csv.apply', $p, fn ($s) => $csv->apply($r->user()->id, $s, $p));
    }
}
