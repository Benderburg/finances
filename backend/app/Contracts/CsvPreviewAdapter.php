<?php

namespace App\Contracts;

// Stage B: preview/mapping/possible duplicates precede a single atomic additive batch.
interface CsvPreviewAdapter
{
    public function preview(string $contents, array $mapping, string $accountId): array;
}
