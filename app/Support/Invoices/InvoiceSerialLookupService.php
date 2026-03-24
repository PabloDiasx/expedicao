<?php

namespace App\Support\Invoices;

use App\Models\FiscalInvoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class InvoiceSerialLookupService
{
    /**
     * @return array{
     *     matched: bool,
     *     multiple: bool,
     *     matched_count: int,
     *     invoice_id: int|null,
     *     invoice_external_id: int|null,
     *     invoice_number: string|null,
     *     customer_name: string|null,
     *     destination: string|null
     * }
     */
    public function findBySerial(int $tenantId, string $serialNumber): array
    {
        $serialRaw = trim($serialNumber);
        $serialKey = $this->normalizeSerialKey($serialRaw);
        $serialFinalDigits = $this->extractFinalSerialDigits($serialRaw);

        if ($serialKey === '' && $serialFinalDigits === null) {
            return $this->emptyResult();
        }

        $version = FiscalInvoice::query()
            ->where('tenant_id', $tenantId)
            ->max('updated_at');

        $versionKey = $version ? (string) strtotime((string) $version) : '0';
        $cacheKey = sprintf(
            'invoice:serial-lookup:%d:%s:%s:%s',
            $tenantId,
            sha1($serialKey),
            sha1((string) $serialFinalDigits),
            $versionKey
        );

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($tenantId, $serialRaw, $serialKey, $serialFinalDigits): array {
            return $this->resolveSerialLookup($tenantId, $serialRaw, $serialKey, $serialFinalDigits);
        });
    }

    /**
     * @return array{
     *     matched: bool,
     *     multiple: bool,
     *     matched_count: int,
     *     invoice_id: int|null,
     *     invoice_external_id: int|null,
     *     invoice_number: string|null,
     *     customer_name: string|null,
     *     destination: string|null
     * }
     */
    private function resolveSerialLookup(
        int $tenantId,
        string $serialRaw,
        string $serialKey,
        ?string $serialFinalDigits
    ): array {
        $candidates = FiscalInvoice::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.xml')) LIKE ?", ['%'.$serialRaw.'%'])
            ->orderByDesc('nomus_updated_at')
            ->orderByDesc('external_id')
            ->limit(30)
            ->get(['id', 'external_id', 'numero', 'payload', 'nomus_updated_at', 'updated_at']);

        if ($candidates->isEmpty()) {
            $candidates = FiscalInvoice::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('nomus_updated_at')
                ->orderByDesc('external_id')
                ->limit(400)
                ->get(['id', 'external_id', 'numero', 'payload', 'nomus_updated_at', 'updated_at']);
        }

        $matches = [];
        foreach ($candidates as $invoice) {
            $summary = $this->extractInvoiceSummary($invoice);
            $invoiceSerialKeys = isset($summary['serial_keys']) && is_array($summary['serial_keys'])
                ? $summary['serial_keys']
                : [];
            $invoiceFinalDigits = isset($summary['serial_final_digits']) && is_array($summary['serial_final_digits'])
                ? $summary['serial_final_digits']
                : [];

            $exactMatch = $serialKey !== '' && in_array($serialKey, $invoiceSerialKeys, true);
            $digitsMatch = ! $exactMatch
                && $serialFinalDigits !== null
                && in_array($serialFinalDigits, $invoiceFinalDigits, true);

            if (! $exactMatch && ! $digitsMatch) {
                continue;
            }

            $matches[] = [
                'invoice' => $invoice,
                'summary' => $summary,
                'priority' => $exactMatch ? 2 : 1,
            ];
        }

        if ($matches === []) {
            return $this->emptyResult();
        }

        usort($matches, static function (array $left, array $right): int {
            return ($right['priority'] <=> $left['priority']);
        });

        $bestPriority = (int) ($matches[0]['priority'] ?? 0);
        $bestMatches = array_values(array_filter(
            $matches,
            static fn (array $item): bool => (int) ($item['priority'] ?? 0) === $bestPriority
        ));

        $first = $bestMatches[0];
        /** @var FiscalInvoice $invoice */
        $invoice = $first['invoice'];
        /** @var array{customer_name:string, destination:string, serial_keys:array<int, string>} $summary */
        $summary = $first['summary'];

        return [
            'matched' => true,
            'multiple' => count($bestMatches) > 1,
            'matched_count' => count($bestMatches),
            'invoice_id' => (int) $invoice->id,
            'invoice_external_id' => (int) $invoice->external_id,
            'invoice_number' => $invoice->numero ? trim((string) $invoice->numero) : null,
            'customer_name' => $summary['customer_name'] !== '' ? $summary['customer_name'] : null,
            'destination' => $summary['destination'] !== '' ? $summary['destination'] : null,
        ];
    }

    /**
     * @return array{
     *     customer_name: string,
     *     destination: string,
     *     serial_keys: array<int, string>,
     *     serial_final_digits: array<int, string>
     * }
     */
    private function extractInvoiceSummary(FiscalInvoice $invoice): array
    {
        $version = $invoice->nomus_updated_at?->timestamp
            ?? $invoice->updated_at?->timestamp
            ?? 0;
        $cacheKey = sprintf('invoice:serial-summary:%d:%d', (int) $invoice->id, (int) $version);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($invoice): array {
            $payload = $invoice->payload;
            $xmlRaw = is_array($payload) ? ($payload['xml'] ?? null) : null;

            if (! is_string($xmlRaw) || trim($xmlRaw) === '') {
                return [
                    'customer_name' => '',
                    'destination' => '',
                    'serial_keys' => [],
                    'serial_final_digits' => [],
                ];
            }

            try {
                $doc = new \DOMDocument();
                $loaded = $doc->loadXML($xmlRaw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                if (! $loaded) {
                    return [
                        'customer_name' => '',
                        'destination' => '',
                        'serial_keys' => [],
                        'serial_final_digits' => [],
                    ];
                }

                $xpath = new \DOMXPath($doc);
                $customerName = $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="xNome"]');
                $city = $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="xMun"]');
                $state = $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="UF"]');
                $destination = trim($city.($state !== '' ? ' - '.$state : ''));
                if ($destination === '') {
                    $destination = $customerName;
                }

                $serialKeys = [];
                $serialFinalDigits = [];
                $itemNodes = $xpath->query('//*[local-name()="det"]');
                if ($itemNodes !== false) {
                    foreach ($itemNodes as $itemNode) {
                        if (! $itemNode instanceof \DOMElement) {
                            continue;
                        }

                        $descricao = $this->firstText($xpath, './/*[local-name()="prod"]/*[local-name()="xProd"]', $itemNode);
                        $infoAdicional = $this->firstText($xpath, './/*[local-name()="infAdProd"]', $itemNode);
                        $serials = array_merge(
                            $this->extractSerialNumbers($descricao),
                            $this->extractSerialNumbers($infoAdicional)
                        );

                        foreach ($serials as $serial) {
                            $key = $this->normalizeSerialKey($serial);
                            if ($key !== '') {
                                $serialKeys[$key] = true;
                            }

                            $finalDigits = $this->extractFinalSerialDigits($serial);
                            if ($finalDigits !== null) {
                                $serialFinalDigits[$finalDigits] = true;
                            }
                        }
                    }
                }

                return [
                    'customer_name' => $customerName,
                    'destination' => $destination,
                    'serial_keys' => array_values(array_keys($serialKeys)),
                    'serial_final_digits' => array_values(array_keys($serialFinalDigits)),
                ];
            } catch (Throwable) {
                return [
                    'customer_name' => '',
                    'destination' => '',
                    'serial_keys' => [],
                    'serial_final_digits' => [],
                ];
            }
        });
    }

    private function firstText(\DOMXPath $xpath, string $path, ?\DOMNode $contextNode = null): string
    {
        $nodeList = $xpath->query($path, $contextNode);
        if ($nodeList === false || $nodeList->length === 0) {
            return '';
        }

        return trim((string) $nodeList->item(0)?->textContent);
    }

    /**
     * @return array<int, string>
     */
    private function extractSerialNumbers(string $text): array
    {
        $value = Str::upper(Str::ascii(trim($text)));
        if ($value === '') {
            return [];
        }

        $matches = [];
        preg_match_all(
            '/\bN\s*O?\.?\s*SERIE\s*[:\-]?\s*([A-Z0-9][A-Z0-9.\-\/]*)/',
            $value,
            $matches
        );

        $serials = [];
        if (isset($matches[1]) && is_array($matches[1])) {
            foreach ($matches[1] as $serial) {
                $normalized = trim((string) $serial);
                if ($normalized !== '') {
                    $serials[$normalized] = true;
                }
            }
        }

        preg_match_all('/\b[A-Z]+[0-9]+\.[0-9]{2}\.[0-9]{1,8}\b/', $value, $directMatches);
        if (isset($directMatches[0]) && is_array($directMatches[0])) {
            foreach ($directMatches[0] as $serial) {
                $normalized = trim((string) $serial);
                if ($normalized !== '') {
                    $serials[$normalized] = true;
                }
            }
        }

        return array_values(array_keys($serials));
    }

    private function normalizeSerialKey(string $value): string
    {
        $normalized = Str::upper(Str::ascii(trim($value)));

        return preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';
    }

    private function extractFinalSerialDigits(string $value): ?string
    {
        $normalized = Str::upper(Str::ascii(trim($value)));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/\.([0-9]{1,8})$/', $normalized, $matches) === 1) {
            $digits = ltrim((string) $matches[1], '0');

            return $digits === '' ? '0' : $digits;
        }

        if (preg_match('/([0-9]{1,8})$/', $normalized, $matches) === 1) {
            $digits = ltrim((string) $matches[1], '0');

            return $digits === '' ? '0' : $digits;
        }

        return null;
    }

    /**
     * @return array{
     *     matched: bool,
     *     multiple: bool,
     *     matched_count: int,
     *     invoice_id: int|null,
     *     invoice_external_id: int|null,
     *     invoice_number: string|null,
     *     customer_name: string|null,
     *     destination: string|null
     * }
     */
    private function emptyResult(): array
    {
        return [
            'matched' => false,
            'multiple' => false,
            'matched_count' => 0,
            'invoice_id' => null,
            'invoice_external_id' => null,
            'invoice_number' => null,
            'customer_name' => null,
            'destination' => null,
        ];
    }
}
