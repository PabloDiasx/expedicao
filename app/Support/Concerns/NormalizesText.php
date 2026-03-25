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
        if ($normalized === '') {
            return null;
        }

        if (function_exists('normalizer_normalize')) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }

        return $normalized;
    }

    private function normalizeScannerCode(string $value): string
    {
        $normalized = trim($value);

        if (function_exists('normalizer_normalize')) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }

        $normalized = mb_strtoupper($normalized);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
