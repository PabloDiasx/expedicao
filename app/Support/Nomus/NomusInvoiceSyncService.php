<?php

namespace App\Support\Nomus;

use App\Models\FiscalInvoice;
use App\Models\InvoiceSyncState;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class NomusInvoiceSyncService
{
    public function __construct(
        private readonly NomusApiClient $apiClient,
    ) {
    }

    /**
     * @return array{
     *     tenant_id: int,
     *     total_processed: int,
     *     created: int,
     *     updated: int,
     *     pages: int,
     *     from: string,
     *     max_modified_at: string|null
     * }
     */
    public function syncTenant(Tenant $tenant, bool $fullSync = false): array
    {
        $state = InvoiceSyncState::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'last_items_count' => 0,
                'last_created_count' => 0,
                'last_updated_count' => 0,
                'last_pages_count' => 0,
            ]
        );

        $now = now();
        $state->forceFill([
            'last_run_at' => $now,
            'last_error' => null,
        ])->save();

        $from = $this->resolveSyncStartingPoint($state, $fullSync);
        $query = 'dataModificacao>'.$from->format('Y-m-d\TH:i:s');

        $created = 0;
        $updated = 0;
        $processed = 0;
        $pageCount = 0;
        $maxModifiedAt = $state->last_synced_modified_at?->toImmutable();

        try {
            for ($page = 1; ; $page++) {
                $rows = $this->apiClient->listInvoices($page, $query);
                $pageCount++;

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $invoiceData) {
                    $upsert = $this->upsertInvoice($tenant->id, $invoiceData, $now);
                    $created += $upsert['created'];
                    $updated += $upsert['updated'];
                    $processed++;

                    $modifiedAt = $upsert['nomus_updated_at'];
                    if ($modifiedAt !== null && ($maxModifiedAt === null || $modifiedAt->gt($maxModifiedAt))) {
                        $maxModifiedAt = $modifiedAt;
                    }
                }
            }
        } catch (Throwable $exception) {
            $state->forceFill([
                'last_error' => mb_substr($exception->getMessage(), 0, 1900),
                'last_items_count' => $processed,
                'last_created_count' => $created,
                'last_updated_count' => $updated,
                'last_pages_count' => $pageCount,
            ])->save();

            throw $exception;
        }

        $state->forceFill([
            'last_success_at' => $now,
            'last_items_count' => $processed,
            'last_created_count' => $created,
            'last_updated_count' => $updated,
            'last_pages_count' => $pageCount,
            'last_error' => null,
            'last_synced_modified_at' => $maxModifiedAt?->toDateTimeString(),
        ])->save();

        return [
            'tenant_id' => (int) $tenant->id,
            'total_processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'pages' => $pageCount,
            'from' => $from->toDateTimeString(),
            'max_modified_at' => $maxModifiedAt?->toDateTimeString(),
        ];
    }

    private function resolveSyncStartingPoint(InvoiceSyncState $state, bool $fullSync): CarbonImmutable
    {
        $lookbackDays = max(1, (int) config('services.nomus.initial_lookback_days', 30));
        $overlapMinutes = max(0, (int) config('services.nomus.sync_overlap_minutes', 2));

        if ($fullSync || ! $state->last_synced_modified_at) {
            return now()->subDays($lookbackDays)->toImmutable();
        }

        return $state->last_synced_modified_at->toImmutable()->subMinutes($overlapMinutes);
    }

    /**
     * @param  array<string, mixed>  $invoiceData
     * @return array{created:int, updated:int, nomus_updated_at: CarbonImmutable|null}
     */
    private function upsertInvoice(int $tenantId, array $invoiceData, Carbon $syncedAt): array
    {
        $externalId = isset($invoiceData['id']) ? (int) $invoiceData['id'] : 0;
        if ($externalId <= 0) {
            throw new RuntimeException('Nota fiscal recebida sem id valido.');
        }

        $nomusCreatedAt = $this->parseDateTime($invoiceData['dataCriacao'] ?? null);
        $nomusUpdatedAt = $this->parseDateTime($invoiceData['dataModificacao'] ?? null);
        $dataProcessamento = $this->parseDate($invoiceData['dataProcessamento'] ?? null);

        $attributes = [
            'numero' => $this->asNullableString($invoiceData['numero'] ?? null),
            'serie' => $this->asNullableString($invoiceData['serie'] ?? null),
            'chave' => $this->asNullableString($invoiceData['chave'] ?? null),
            'cnpj_emitente' => $this->asNullableString($invoiceData['cnpjEmitente'] ?? null),
            'protocolo' => $this->asNullableString($invoiceData['protocolo'] ?? null),
            'recibo' => $this->asNullableString($invoiceData['recibo'] ?? null),
            'ambiente' => $this->asNullableInt($invoiceData['ambiente'] ?? null),
            'finalidade' => $this->asNullableInt($invoiceData['finalidade'] ?? null),
            'status' => $this->asNullableInt($invoiceData['status'] ?? null),
            'tipo_emissao' => $this->asNullableInt($invoiceData['tipoEmissao'] ?? null),
            'tipo_operacao' => $this->asNullableInt($invoiceData['tipoOperacao'] ?? null),
            'is_fornecedor' => ((int) ($invoiceData['isFornecedor'] ?? 0)) === 1,
            'usuario' => $this->asNullableString($invoiceData['usuario'] ?? null),
            'data_processamento' => $dataProcessamento,
            'hora_processamento' => $this->asNullableString($invoiceData['horaProcessamento'] ?? null),
            'nomus_created_at' => $nomusCreatedAt?->toDateTimeString(),
            'nomus_updated_at' => $nomusUpdatedAt?->toDateTimeString(),
            'payload' => $invoiceData,
            'last_synced_at' => $syncedAt->toDateTimeString(),
        ];

        return DB::transaction(function () use ($tenantId, $externalId, $attributes, $nomusUpdatedAt): array {
            $invoice = FiscalInvoice::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                FiscalInvoice::query()->create([
                    'tenant_id' => $tenantId,
                    'external_id' => $externalId,
                    ...$attributes,
                ]);

                return [
                    'created' => 1,
                    'updated' => 0,
                    'nomus_updated_at' => $nomusUpdatedAt,
                ];
            }

            $invoice->fill($attributes);

            if ($invoice->isDirty()) {
                $invoice->save();

                return [
                    'created' => 0,
                    'updated' => 1,
                    'nomus_updated_at' => $nomusUpdatedAt,
                ];
            }

            return [
                'created' => 0,
                'updated' => 0,
                'nomus_updated_at' => $nomusUpdatedAt,
            ];
        });
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        $timezone = config('app.timezone', 'America/Sao_Paulo');

        $formats = [
            '!d/m/Y H:i:s',
            '!d/m/Y H:i',
            '!Y-m-d H:i:s',
            DATE_ATOM,
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, $timezone);
                if ($parsed && $parsed->format(ltrim($format, '!')) === $raw) {
                    return $parsed;
                }
            } catch (Throwable) {
                // continue
            }
        }

        try {
            return CarbonImmutable::parse($raw, $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        $timezone = config('app.timezone', 'America/Sao_Paulo');

        $formats = [
            '!d/m/Y',
            '!Y-m-d',
            DATE_ATOM,
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, $timezone);
                if ($parsed && $parsed->format(ltrim($format, '!')) === $raw) {
                    return $parsed->toDateString();
                }
            } catch (Throwable) {
                // continue
            }
        }

        try {
            return CarbonImmutable::parse($raw, $timezone)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function asNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function asNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
