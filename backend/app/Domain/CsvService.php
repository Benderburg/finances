<?php

namespace App\Domain;

use App\Contracts\CsvPreviewAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CsvService implements CsvPreviewAdapter
{
    public const HEADERS = ['operation_id', 'type', 'date', 'account_id', 'account_name', 'amount', 'currency', 'category_code', 'category_name', 'description', 'from_account_id', 'from_account_name', 'to_account_id', 'to_account_name', 'target_amount', 'target_currency', 'effective_rate', 'status', 'goal_id', 'liability_id', 'goal_completion_requested'];

    public function __construct(private readonly FinancialEngine $engine, private readonly CommandBus $bus) {}

    private function decimal(?string $minor): string
    {
        if ($minor === null) {
            return '';
        }

        return substr(str_pad($minor, 3, '0', STR_PAD_LEFT), 0, -2).'.'.substr(str_pad($minor, 3, '0', STR_PAD_LEFT), -2);
    }

    public function export(string $user, bool $safe = true): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['#norocel-csv', '1', $safe ? 'excel-safe' : 'raw'], ',', '"', '', "\r\n");
        fputcsv($out, self::HEADERS, ',', '"', '', "\r\n");
        $accounts = DB::table('accounts')->where('user_id', $user)->get()->keyBy('id');
        $categories = DB::table('categories')->where('user_id', $user)->get()->keyBy('id');
        $links = DB::table('liability_settlements')->where('user_id', $user)->pluck('liability_id', 'operation_id');
        foreach (DB::table('operations')->where('user_id', $user)->orderBy('occurred_on')->orderBy('id')->cursor() as $o) {
            $row = [$o->id, $o->type, $o->occurred_on, $o->account_id ?? '', $accounts[$o->account_id]->name ?? '', $this->decimal((string) $o->amount_minor), $o->currency_code, $categories[$o->category_id]->system_code ?? '', $categories[$o->category_id]->name ?? '', $o->description, $o->from_account_id ?? '', $accounts[$o->from_account_id]->name ?? '', $o->to_account_id ?? '', $accounts[$o->to_account_id]->name ?? '', $this->decimal($o->target_amount_minor === null ? null : (string) $o->target_amount_minor), $o->target_currency_code ?? '', $o->effective_rate ?? '', $o->status, $o->goal_id ?? '', $links[$o->id] ?? '', $o->goal_completion_requested ? '1' : '0'];
            foreach ([4, 8, 9, 11, 13] as $i) {
                // Prefix all user text, including an existing apostrophe: reversible, no heuristics.
                if ($safe && $row[$i] !== '') {
                    $row[$i] = "'".$row[$i];
                }
            }
            fputcsv($out, $row, ',', '"', '', "\r\n");
        }
        rewind($out);
        $contents = stream_get_contents($out);
        fclose($out);

        return $contents;
    }

    private function parse(string $contents, array $o): array
    {
        if (strlen($contents) > 5 * 1024 * 1024) {
            throw new DomainError('CSV_LIMIT');
        }
        $encoding = $o['encoding'] ?? (str_starts_with($contents, "\xFF\xFE") ? 'UTF-16LE' : (str_starts_with($contents, "\xFE\xFF") ? 'UTF-16BE' : (mb_check_encoding($contents, 'UTF-8') ? 'UTF-8' : 'Windows-1251')));
        if (! in_array($encoding, ['UTF-8', 'Windows-1251', 'Windows-1250', 'UTF-16LE', 'UTF-16BE'])) {
            throw new DomainError('CSV_ENCODING');
        }
        $text = iconv($encoding, 'UTF-8', $contents);
        if ($text === false || ! mb_check_encoding($text, 'UTF-8')) {
            throw new DomainError('CSV_ENCODING');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $delimiter = $o['delimiter'] ?? null;
        if ($delimiter === null) {
            $line = strtok($text, "\r\n");
            $score = -1;
            $delimiter = ',';
            foreach ([',', ';', "\t", '|'] as $candidate) {
                $n = count(str_getcsv($line ?: '', $candidate, '"', ''));
                if ($n > $score) {
                    $score = $n;
                    $delimiter = $candidate;
                }
            }
        }
        if (! in_array($delimiter, [',', ';', "\t", '|'], true)) {
            throw new DomainError('CSV_DELIMITER');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $text);
        rewind($stream);
        $header = fgetcsv($stream, null, $delimiter, '"', '');
        $own = ($header[0] ?? null) === '#norocel-csv';
        $safe = false;
        if ($own) {
            if (($header[1] ?? '') !== '1' || ! in_array($header[2] ?? '', ['raw', 'excel-safe'])) {
                throw new DomainError('CSV_VERSION');
            }
            $safe = $header[2] === 'excel-safe';
            $header = fgetcsv($stream, null, $delimiter, '"', '');
        }
        if (! $header || count($header) > 100 || count(array_unique($header)) !== count($header) || in_array('', $header, true)) {
            throw new DomainError('CSV_HEADERS');
        }
        foreach ($header as $h) {
            if (strlen($h) > 255) {
                throw new DomainError('CSV_HEADERS');
            }
        }
        if ($own && array_diff(array_slice(self::HEADERS, 0, 20), $header)) {
            throw new DomainError('CSV_HEADERS');
        }
        $rows = [];
        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            if (count($rows) >= 10000) {
                throw new DomainError('CSV_LIMIT');
            }
            $row = count($values) === count($header) ? array_combine($header, $values) : null;
            if ($row && $safe) {
                foreach (['account_name', 'category_name', 'description', 'from_account_name', 'to_account_name'] as $field) {
                    if (str_starts_with($row[$field] ?? '', "'")) {
                        $row[$field] = substr($row[$field], 1);
                    }
                }
            }
            $rows[] = $row;
        }
        fclose($stream);
        $sourceAccounts = [];
        if ($own) {
            foreach ($rows as $r) {
                if (! $r) {
                    continue;
                }
                foreach (['account', 'from_account', 'to_account'] as $prefix) {
                    if ($r[$prefix.'_id']) {
                        $sourceAccounts[$r[$prefix.'_id']] = ['id' => $r[$prefix.'_id'], 'name' => $r[$prefix.'_name'], 'currency_code' => $prefix === 'to_account' ? $r['target_currency'] : $r['currency']];
                    }
                }
            }
        }

        return ['encoding' => $encoding, 'delimiter' => $delimiter, 'format' => $own ? 'norocel-1' : 'bank', 'headers' => $header, 'sample' => array_slice($rows, 0, 5), 'source_accounts' => array_values($sourceAccounts), 'count' => count($rows), 'rows' => $rows];
    }

    private function fingerprint(array $op): string
    {
        $description = mb_strtolower(preg_replace('/\s+/u', ' ', trim($op['description'])));

        return hash('sha256', implode('|', [$op['account_id'] ?? $op['from_account_id'], $op['occurred_on'], $op['type'], $op['amount_minor'], $op['currency_code'], $description]));
    }

    private function category(string $user, string $kind, string $code, string $name, ?string $fallback): string
    {
        $q = DB::table('categories')->where('user_id', $user)->where('kind', $kind);
        if ($code !== '') {
            $q->where('system_code', $code);
        } elseif ($name !== '') {
            $q->where('name', $name);
        } elseif ($fallback) {
            return $fallback;
        } else {
            throw new DomainError('CSV_CATEGORY_MAPPING');
        }
        $matches = $q->get();
        if ($matches->count() !== 1) {
            throw new DomainError('CSV_CATEGORY_MAPPING');
        }

        return $matches[0]->id;
    }

    private function normalize(string $user, array $row, array $o, bool $own): array
    {
        $liability = null;
        $operationId = null;
        $bankId = null;
        if ($own) {
            $operationId = $row['operation_id'];
            if (! Str::isUuid($operationId)) {
                throw new DomainError('CSV_OPERATION_ID');
            }
            $status = $row['status'];
            if (! in_array($status, ['posted', 'voided'])) {
                throw new DomainError('CSV_STATUS');
            }
            $type = $row['type'];
            $single = in_array($type, ['income', 'expense']);
            $account = fn ($id) => ($o['account_mappings'][$id] ?? $id) ?: null;
            $op = ['type' => $type, 'occurred_on' => $row['date'], 'amount_minor' => Money::decimal($row['amount']), 'currency_code' => $row['currency'], 'description' => $row['description'], 'account_id' => $account($row['account_id']), 'from_account_id' => $account($row['from_account_id']), 'to_account_id' => $account($row['to_account_id']), 'category_id' => $single ? $this->category($user, $type, $row['category_code'], $row['category_name'], $o[$type.'_category_id'] ?? null) : null, 'goal_id' => $row['goal_id'] ?: null, 'goal_completion_requested' => ($row['goal_completion_requested'] ?? '0') === '1'];
            if ($row['target_amount'] !== '') {
                $op['target_amount_minor'] = Money::decimal($row['target_amount']);
            }
            if ($row['target_currency'] !== '') {
                $op['target_currency_code'] = $row['target_currency'];
            }
            $liability = $row['liability_id'] ?: null;
            if ($op['goal_id'] && ! DB::table('goals')->where('user_id', $user)->where('id', $op['goal_id'])->exists()) {
                throw new DomainError('CSV_CONTEXT_REQUIRES_JSON');
            }
        } else {
            $map = $o['mapping'] ?? [];
            $v = fn ($key) => trim($row[$map[$key] ?? ''] ?? '');
            $value = str_replace(["\xC2\xA0", ' '], '', $v('amount'));
            if (($o['decimal_separator'] ?? '.') === ',') {
                $value = str_replace(',', '.', $value);
            }
            if (! preg_match('/^[+-]?[0-9]{1,15}(\.[0-9]{1,2})?$/D', $value)) {
                throw new DomainError('INVALID_MONEY');
            }
            $direction = mb_strtolower($v('direction'));
            if (preg_match('/\b(transfer|exchange|перевод|обмен|schimb|sepa|swift)\b/ui', $direction.' '.$v('description'))) {
                throw new DomainError('CSV_TRANSFER_OR_DIRECTION');
            }
            if ($direction !== '') {
                $type = match ($direction) {
                    mb_strtolower($o['income_value'] ?? 'income'), 'credit', 'income', 'доход' => 'income',
                    mb_strtolower($o['expense_value'] ?? 'expense'), 'debit', 'expense', 'расход' => 'expense',
                    default => throw new DomainError('CSV_TRANSFER_OR_DIRECTION'),
                };
                if ($type === 'income' && str_starts_with($value, '-')) {
                    throw new DomainError('CSV_DIRECTION_CONFLICT');
                }
            } elseif (isset($map['direction']) && $map['direction'] !== '') {
                throw new DomainError('CSV_TRANSFER_OR_DIRECTION');
            } else {
                $type = str_starts_with($value, '-') ? 'expense' : 'income';
            }
            $dateFormat = $o['date_format'] ?? 'Y-m-d';
            if (! in_array($dateFormat, ['Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y'])) {
                throw new DomainError('CSV_DATE');
            }
            $date = CarbonImmutable::createFromFormat('!'.$dateFormat, $v('date'));
            if (! $date || $date->format($dateFormat) !== $v('date')) {
                throw new DomainError('CSV_DATE');
            }
            $account = $this->engine->owned('accounts', $user, $o['account_id'] ?? '');
            $op = ['type' => $type, 'occurred_on' => $date->toDateString(), 'account_id' => $account['id'], 'amount_minor' => Money::decimal(ltrim($value, '-+')), 'currency_code' => $v('currency') ?: $account['currency_code'], 'description' => $v('description'), 'category_id' => $this->category($user, $type, '', $v('category'), $o[$type.'_category_id'] ?? null)];
            $bankId = $v('transaction_id') ?: null;
            if ($bankId !== null && strlen($bankId) > 255) {
                throw new DomainError('CSV_OPERATION_ID');
            }
            $status = 'posted';
        }
        $op = $this->engine->normalize($user, $op, null, ! empty($op['goal_id']));
        if ($liability) {
            if ($op['goal_id'] || ! in_array($op['type'], ['income', 'expense'])) {
                throw new DomainError('INVALID_LIABILITY_LINK');
            }
            $l = DB::table('liabilities')->where('user_id', $user)->where('id', $liability)->first();
            if (! $l) {
                throw new DomainError('CSV_CONTEXT_REQUIRES_JSON');
            }
            if ($status !== 'posted' || $l->cancelled_at || DB::table('liability_settlements')->where('user_id', $user)->where('liability_id', $liability)->exists() || $l->currency_code !== $op['currency_code'] || (string) $l->principal_minor !== $op['amount_minor'] || ($l->kind === 'receivable' ? 'income' : 'expense') !== $op['type']) {
                throw new DomainError('INVALID_LIABILITY_LINK');
            }
        }
        $keys = [];
        if ($operationId) {
            $keys[] = hash('sha256', 'own:'.$operationId);
        }
        if ($bankId) {
            $keys[] = hash('sha256', 'bank:'.$op['account_id'].':'.$bankId);
        }

        return ['operation' => $op, 'status' => $status, 'operation_id' => $operationId, 'liability_id' => $liability, 'strict_keys' => $keys, 'fingerprint' => $this->fingerprint($op)];
    }

    public function preview(string $user, string $contents, array $options): array
    {
        Validator::make($options, ['confirmed' => 'sometimes|boolean', 'mapping' => 'sometimes|array', 'mapping.*' => 'nullable|string|max:255', 'account_mappings' => 'sometimes|array', 'account_mappings.*' => 'required|uuid', 'account_id' => 'nullable|uuid', 'income_category_id' => 'nullable|uuid', 'expense_category_id' => 'nullable|uuid', 'income_value' => 'sometimes|string|min:1|max:64', 'expense_value' => 'sometimes|string|min:1|max:64', 'date_format' => 'sometimes|in:Y-m-d,d.m.Y,d/m/Y,m/d/Y'])->validate();
        if (isset($options['decimal_separator']) && ! in_array($options['decimal_separator'], ['.', ','], true)) {
            throw new DomainError('CSV_INVALID_ROW');
        }
        if (isset($options['income_value'], $options['expense_value']) && mb_strtolower($options['income_value']) === mb_strtolower($options['expense_value'])) {
            throw new DomainError('CSV_DIRECTION_CONFLICT');
        }
        $parsed = $this->parse($contents, $options);
        $rows = $parsed['rows'];
        unset($parsed['rows']);
        if (($options['confirmed'] ?? false) !== true) {
            return $parsed + ['needs_confirmation' => true];
        }

        return $this->bus->snapshot($user, function ($settings) use ($user, $contents, $options, $parsed, $rows) {
            $known = [];
            foreach (DB::table('operations')->where('user_id', $user)->cursor() as $existing) {
                $known[$this->fingerprint((array) $existing)] = true;
            }
            $seenStrict = [];
            $seenHashes = [];
            $entries = [];
            $sourceHash = hash('sha256', $contents);
            foreach ($rows as $i => $row) {
                $entry = ['number' => $i + 1, 'errors' => [], 'possible_duplicate' => false, 'source_key' => hash('sha256', $sourceHash.':'.($i + 1))];
                try {
                    if ($row === null) {
                        throw new DomainError('CSV_COLUMN_COUNT');
                    }
                    $entry += $this->normalize($user, $row, $options, $parsed['format'] === 'norocel-1');
                    if ($entry['operation_id'] && DB::table('operations')->where('user_id', $user)->where('id', $entry['operation_id'])->exists()) {
                        throw new DomainError('CSV_STRICT_DUPLICATE');
                    }
                    foreach (array_merge([$entry['source_key']], $entry['strict_keys']) as $key) {
                        if (isset($seenStrict[$key]) || DB::table('csv_import_rows')->where('user_id', $user)->where('source_key', $key)->exists()) {
                            throw new DomainError('CSV_STRICT_DUPLICATE');
                        }
                        $seenStrict[$key] = true;
                    }
                    $entry['possible_duplicate'] = isset($known[$entry['fingerprint']]) || isset($seenHashes[$entry['fingerprint']]);
                    $seenHashes[$entry['fingerprint']] = true;
                } catch (\Throwable $e) {
                    $entry['errors'][] = $e instanceof DomainError ? $e->errorCode : ($e instanceof ValidationException ? 'VALIDATION_FAILED' : 'CSV_INVALID_ROW');
                }
                $entries[] = $entry;
            }
            DB::table('csv_previews')->where('user_id', $user)->where('expires_at', '<', now())->delete();
            $id = (string) Str::uuid();
            DB::table('csv_previews')->insert(['id' => $id, 'user_id' => $user, 'generation' => $settings->workspace_generation, 'workspace_revision' => $settings->workspace_revision, 'source_hash' => $sourceHash, 'payload' => json_encode($entries, JSON_THROW_ON_ERROR), 'expires_at' => now()->addMinutes(30)]);

            return $parsed + ['needs_confirmation' => false, 'preview_id' => $id, 'workspace_revision' => (string) $settings->workspace_revision, 'rows' => $entries];
        })['data'];
    }

    public function apply(string $user, object $settings, array $p): array
    {
        $preview = DB::table('csv_previews')->where('user_id', $user)->where('id', $p['preview_id'])->first();
        if (! $preview || $preview->expires_at < now()->toDateTimeString()) {
            throw new DomainError('CSV_PREVIEW_EXPIRED', 409);
        }
        if ((string) $preview->generation !== (string) $settings->workspace_generation) {
            throw new DomainError('WORKSPACE_REPLACED', 409);
        }
        if ((string) $preview->workspace_revision !== (string) $settings->workspace_revision) {
            throw new DomainError('CSV_PREVIEW_STALE', 409);
        }
        $rows = json_decode($preview->payload, true, 512, JSON_THROW_ON_ERROR);
        $selected = array_values(array_unique($p['selected_rows']));
        sort($selected);
        $entries = [];
        $keys = [];
        $liabilities = [];
        foreach ($selected as $number) {
            $row = $rows[$number - 1] ?? null;
            if (! $row || $row['errors']) {
                throw new DomainError('CSV_INVALID_SELECTION');
            }
            if ($row['possible_duplicate'] && ! $p['accept_possible_duplicates']) {
                throw new DomainError('CSV_DUPLICATES_CONFIRMATION');
            }
            foreach (array_merge([$row['source_key']], $row['strict_keys']) as $key) {
                $receipt = DB::table('csv_import_rows')->where('user_id', $user)->where('source_key', $key)->first();
                if ($receipt) {
                    throw new DomainError((string) $receipt->generation !== (string) $settings->workspace_generation ? 'WORKSPACE_REPLACED' : 'CSV_STRICT_DUPLICATE', 409);
                }
                if (isset($keys[$key])) {
                    throw new DomainError('CSV_STRICT_DUPLICATE', 409);
                }
                $keys[$key] = true;
            }
            if ($row['liability_id']) {
                if (isset($liabilities[$row['liability_id']])) {
                    throw new DomainError('INVALID_LIABILITY_LINK');
                }
                $liabilities[$row['liability_id']] = true;
            }
            $entries[] = $row;
        }
        $result = $this->engine->postBatch($user, $entries);
        foreach ($entries as $i => $row) {
            foreach (array_merge([$row['source_key']], $row['strict_keys']) as $key) {
                DB::table('csv_import_rows')->insert(['user_id' => $user, 'source_key' => $key, 'generation' => $settings->workspace_generation, 'operation_id' => $result['operation_ids'][$i], 'created_at' => now()]);
            }
        }

        return $result + ['imported' => count($entries), 'preview_id' => $p['preview_id']];
    }
}
