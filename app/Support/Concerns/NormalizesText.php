<?php

namespace App\Support\Concerns;

trait NormalizesText
{
    private function normalizeNullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeScannerCode(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
