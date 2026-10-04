<?php

namespace App\Contracts;

// Stage B: preview/mapping/possible duplicates precede a single atomic additive batch.
interface CsvPreviewAdapter
{
    public function preview(string $user, string $contents, array $options): array;
}
