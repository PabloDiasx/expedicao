<?php

namespace App\Support\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MontagemPlanningService
{
    public function __construct(
        private readonly EquipmentStatusService $statusService,
    ) {
    }

    /**
     * @return array{
     *     equipment_rows: array<int, array<string, mixed>>,
     *     accessory_rows: array<int, array<string, mixed>>,
     *     recent_scans: \Illuminate\Support\Collection<int, object>,
     *     unmapped_rows: array<int, array<string, mixed>>
     * }
     */
    public function buildDashboard(
        int $tenantId,
        CarbonImmutable $dueFrom,
        CarbonImmutable $dueUntil,
        ?string $search = null
    ): array
    {
        $search = trim((string) $search);
        $pendingStatus = (int) config('services.nomus.pending_item_status', 1);
        $prefix = Str::upper(trim((string) config('services.nomus.sales_order_prefix', 'PD')));
        $prefixLike = $prefix === '' ? '%' : $prefix.'.%';

        $modelIndex = $this->loadModelIndex($tenantId);
        $itemRows = DB::table('nomus_sales_order_items as soi')
            ->join('nomus_sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->leftJoin('nomus_products as np', function ($join) {
                $join->on('np.external_id', '=', 'soi.product_external_id')
                    ->on('np.tenant_id', '=', 'soi.tenant_id');
            })
            ->where('soi.tenant_id', $tenantId)
            ->where('so.codigo_pedido', 'like', $prefixLike)
            ->where('soi.item_status', $pendingStatus)
            ->whereRaw(
                'COALESCE(soi.delivery_date, so.data_entrega_padrao) between ? and ?',
                [$dueFrom->toDateString(), $dueUntil->toDateString()]
            )
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $like = '%'.$search.'%';
                    $inner->where('so.codigo_pedido', 'like', $like)
                        ->orWhere('soi.product_name', 'like', $like)
                        ->orWhere('soi.product_code', 'like', $like)
                        ->orWhere('np.nome', 'like', $like)
                        ->orWhere('np.codigo', 'like', $like)
                        ->orWhere('np.descricao', 'like', $like);
                });
            })
            ->orderByRaw('COALESCE(soi.delivery_date, so.data_entrega_padrao)')
            ->orderBy('so.id')
            ->orderBy('soi.item_code')
            ->get([
                'soi.id',
                'soi.sales_order_id',
                'soi.item_code',
                'soi.product_external_id',
                'soi.quantity',
                'soi.allocated_quantity',
                'soi.product_code',
                'soi.product_name',
                'so.codigo_pedido',
                'so.data_entrega_padrao',
                DB::raw('COALESCE(soi.delivery_date, so.data_entrega_padrao) as entrega_item'),
                'np.codigo as nomus_product_code',
                'np.nome as nomus_product_name',
                'np.descricao as nomus_product_description',
            ]);

        $equipmentRowsByModel = [];
        $componentDemandByName = [];
        $unmappedRows = [];
        $componentDemandInputs = [];

        foreach ($itemRows as $item) {
            $mappedModel = $this->resolveModelForOrderItem($item, $modelIndex);
            if (! $mappedModel) {
                $unmappedRows[] = [
                    'pedido' => (string) $item->codigo_pedido,
                    'item' => (string) $item->item_code,
                    'produto' => (string) ($item->nomus_product_name ?: $item->product_name ?: '-'),
                    'codigo' => (string) ($item->nomus_product_code ?: $item->product_code ?: '-'),
                    'quantidade' => $this->parseDecimal((string) $item->quantity),
                ];

                continue;
            }

            $ordered = $this->parseDecimal((string) $item->quantity);
            $allocated = $this->parseDecimal((string) $item->allocated_quantity);
            $remaining = max(0.0, $ordered - $allocated);
            $deliveryDate = $this->parseDateOrNull((string) $item->entrega_item);

            $bucket = $equipmentRowsByModel[$mappedModel->id] ?? [
                'model_id' => (int) $mappedModel->id,
                'model_name' => (string) $mappedModel->name,
                'ordered' => 0.0,
                'assembled' => 0.0,
                'remaining' => 0.0,
                'next_delivery' => null,
            ];

            $bucket['ordered'] += $ordered;
            $bucket['assembled'] += $allocated;
            $bucket['remaining'] += $remaining;
            if ($remaining > 0 && $deliveryDate !== null) {
                if ($bucket['next_delivery'] === null || $deliveryDate->lt($bucket['next_delivery'])) {
                    $bucket['next_delivery'] = $deliveryDate;
                }
            }

            $equipmentRowsByModel[$mappedModel->id] = $bucket;

            if ($remaining > 0 && $item->product_external_id) {
                $componentDemandInputs[] = [
                    'product_external_id' => (int) $item->product_external_id,
                    'remaining' => $remaining,
                ];
            }
        }

        $componentsByParent = [];
        if ($componentDemandInputs !== []) {
            $parentIds = array_values(array_unique(array_map(
                static fn (array $row): int => (int) $row['product_external_id'],
                $componentDemandInputs
            )));

            $components = DB::table('nomus_product_components')
                ->where('tenant_id', $tenantId)
                ->whereIn('parent_product_external_id', $parentIds)
                ->get([
                    'parent_product_external_id',
                    'component_codigo',
                    'component_nome',
                    'quantity_required',
                ]);

            foreach ($components as $component) {
                $parentId = (int) $component->parent_product_external_id;
                $componentsByParent[$parentId] ??= [];
                $componentsByParent[$parentId][] = $component;
            }
        }

        foreach ($componentDemandInputs as $demandInput) {
            $parentId = (int) $demandInput['product_external_id'];
            $remaining = (float) $demandInput['remaining'];
            $components = $componentsByParent[$parentId] ?? [];

            foreach ($components as $component) {
                $required = $this->parseDecimal((string) $component->quantity_required);
                if ($required <= 0) {
                    continue;
                }

                $componentName = trim((string) ($component->component_nome ?: $component->component_codigo ?: 'Componente sem nome'));
                $planned = $required * $remaining;

                $bucket = $componentDemandByName[$componentName] ?? [
                    'name' => $componentName,
                    'quantity_planned' => 0.0,
                ];
                $bucket['quantity_planned'] += $planned;
                $componentDemandByName[$componentName] = $bucket;
            }
        }

        $equipmentRows = array_values(array_map(function (array $row): array {
            return [
                'model_id' => (int) $row['model_id'],
                'model_name' => $row['model_name'],
                'ordered' => $this->formatQuantity($row['ordered']),
                'assembled' => $this->formatQuantity($row['assembled']),
                'remaining' => $this->formatQuantity($row['remaining']),
                'next_delivery' => $row['next_delivery']?->format('d/m/Y') ?? '-',
                'next_delivery_sort' => $row['next_delivery']?->format('Y-m-d') ?? '9999-12-31',
            ];
        }, $equipmentRowsByModel));

        usort($equipmentRows, static function (array $left, array $right): int {
            return [$left['next_delivery_sort'], $left['model_name']] <=> [$right['next_delivery_sort'], $right['model_name']];
        });

        $accessoryRows = array_values(array_map(function (array $row): array {
            return [
                'name' => $row['name'],
                'quantity_planned' => $this->formatQuantity($row['quantity_planned']),
            ];
        }, $componentDemandByName));

        usort($accessoryRows, static function (array $left, array $right): int {
            return [$right['quantity_planned'], $left['name']] <=> [$left['quantity_planned'], $right['name']];
        });

        $recentScans = DB::table('montagem_scan_events as mse')
            ->join('equipments as e', 'e.id', '=', 'mse.equipment_id')
            ->join('nomus_sales_orders as so', 'so.id', '=', 'mse.sales_order_id')
            ->join('nomus_sales_order_items as soi', 'soi.id', '=', 'mse.sales_order_item_id')
            ->leftJoin('users as u', 'u.id', '=', 'mse.user_id')
            ->where('mse.tenant_id', $tenantId)
            ->orderByDesc('mse.scanned_at')
            ->limit(20)
            ->get([
                'mse.scanned_at',
                'e.serial_number',
                'so.codigo_pedido',
                'soi.item_code',
                'mse.device_identifier',
                'u.name as user_name',
                'mse.notes',
            ]);

        return [
            'equipment_rows' => $equipmentRows,
            'accessory_rows' => $accessoryRows,
            'recent_scans' => $recentScans,
            'unmapped_rows' => $unmappedRows,
        ];
    }

    /**
     * @return array{
     *     result: string,
     *     serial_number: string|null,
     *     message: string,
     *     order_code: string|null,
     *     model_name: string|null
     * }
     */
    public function registerScan(
        int $tenantId,
        ?int $userId,
        string $barcode,
        ?string $deviceIdentifier,
        ?string $notes
    ): array {
        $barcodeInput = trim($barcode);
        if ($barcodeInput === '') {
            return [
                'result' => 'invalid_barcode',
                'serial_number' => null,
                'message' => 'Informe um codigo de barras valido.',
                'order_code' => null,
                'model_name' => null,
            ];
        }

        $montagemStatusId = (int) DB::table('statuses')->where('code', 'montado')->value('id');
        $montagemSectorId = (int) DB::table('sectors')
            ->where('code', 'montagem')
            ->where('is_active', true)
            ->value('id');

        if ($montagemStatusId <= 0 || $montagemSectorId <= 0) {
            return [
                'result' => 'missing_setup',
                'serial_number' => null,
                'message' => 'Configuracao incompleta para a etapa de montagem.',
                'order_code' => null,
                'model_name' => null,
            ];
        }

        $transition = $this->statusService->applyBarcodeTransition(
            tenantId: $tenantId,
            userId: $userId,
            barcode: $barcodeInput,
            toStatusId: $montagemStatusId,
            sectorId: $montagemSectorId,
            deviceIdentifier: $deviceIdentifier,
            notes: $notes,
            eventSource: 'scanner_montagem'
        );

        if (($transition['result'] ?? null) === 'not_found') {
            return [
                'result' => 'not_found',
                'serial_number' => null,
                'message' => 'Nenhum equipamento foi encontrado para o codigo informado.',
                'order_code' => null,
                'model_name' => null,
            ];
        }

        $equipmentId = isset($transition['equipment_id']) ? (int) $transition['equipment_id'] : 0;
        if ($equipmentId <= 0) {
            return [
                'result' => 'invalid_equipment',
                'serial_number' => null,
                'message' => 'Nao foi possivel identificar o equipamento lido.',
                'order_code' => null,
                'model_name' => null,
            ];
        }

        $normalizedBarcode = $this->normalizeScannerCode($barcodeInput);
        $modelIndex = $this->loadModelIndex($tenantId);
        $pendingStatus = (int) config('services.nomus.pending_item_status', 1);
        $prefix = Str::upper(trim((string) config('services.nomus.sales_order_prefix', 'PD')));
        $prefixLike = $prefix === '' ? '%' : $prefix.'.%';

        return DB::transaction(function () use (
            $tenantId,
            $equipmentId,
            $normalizedBarcode,
            $barcodeInput,
            $deviceIdentifier,
            $notes,
            $userId,
            $modelIndex,
            $pendingStatus,
            $prefixLike
        ): array {
            $existingScan = DB::table('montagem_scan_events')
                ->where('tenant_id', $tenantId)
                ->where('equipment_id', $equipmentId)
                ->lockForUpdate()
                ->first(['id']);

            $equipment = DB::table('equipments as e')
                ->join('equipment_models as em', 'em.id', '=', 'e.equipment_model_id')
                ->where('e.tenant_id', $tenantId)
                ->where('e.id', $equipmentId)
                ->lockForUpdate()
                ->first([
                    'e.id',
                    'e.serial_number',
                    'e.equipment_model_id',
                    'em.name as model_name',
                ]);

            if (! $equipment) {
                return [
                    'result' => 'invalid_equipment',
                    'serial_number' => null,
                    'message' => 'Equipamento nao encontrado para o tenant atual.',
                    'order_code' => null,
                    'model_name' => null,
                ];
            }

            if ($existingScan) {
                return [
                    'result' => 'duplicate_scan',
                    'serial_number' => (string) $equipment->serial_number,
                    'message' => 'Este equipamento ja foi baixado na montagem anteriormente.',
                    'order_code' => null,
                    'model_name' => (string) $equipment->model_name,
                ];
            }

            $candidateItems = DB::table('nomus_sales_order_items as soi')
                ->join('nomus_sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
                ->leftJoin('nomus_products as np', function ($join) {
                    $join->on('np.external_id', '=', 'soi.product_external_id')
                        ->on('np.tenant_id', '=', 'soi.tenant_id');
                })
                ->where('soi.tenant_id', $tenantId)
                ->where('soi.item_status', $pendingStatus)
                ->where('so.codigo_pedido', 'like', $prefixLike)
                ->whereRaw('soi.allocated_quantity < soi.quantity')
                ->orderByRaw('COALESCE(soi.delivery_date, so.data_entrega_padrao)')
                ->orderBy('so.id')
                ->orderBy('soi.item_code')
                ->lockForUpdate()
                ->get([
                    'soi.id',
                    'soi.sales_order_id',
                    'soi.item_code',
                    'soi.product_external_id',
                    'soi.product_code',
                    'soi.product_name',
                    'soi.quantity',
                    'soi.allocated_quantity',
                    'so.codigo_pedido',
                    'so.data_entrega_padrao',
                    DB::raw('COALESCE(soi.delivery_date, so.data_entrega_padrao) as entrega_item'),
                    'np.codigo as nomus_product_code',
                    'np.nome as nomus_product_name',
                    'np.descricao as nomus_product_description',
                ]);

            $allocatedItem = null;
            foreach ($candidateItems as $candidate) {
                $mappedModel = $this->resolveModelForOrderItem($candidate, $modelIndex);
                if (! $mappedModel || (int) $mappedModel->id !== (int) $equipment->equipment_model_id) {
                    continue;
                }

                $affected = DB::table('nomus_sales_order_items')
                    ->where('id', $candidate->id)
                    ->whereRaw('allocated_quantity + 1 <= quantity')
                    ->update([
                        'allocated_quantity' => DB::raw('allocated_quantity + 1'),
                        'updated_at' => now(),
                    ]);

                if ($affected === 1) {
                    $allocatedItem = $candidate;
                    break;
                }
            }

            if (! $allocatedItem) {
                return [
                    'result' => 'no_pending_demand',
                    'serial_number' => (string) $equipment->serial_number,
                    'message' => 'Nao existe saldo pendente para este modelo nos pedidos em aberto.',
                    'order_code' => null,
                    'model_name' => (string) $equipment->model_name,
                ];
            }

            DB::table('montagem_scan_events')->insert([
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentId,
                'equipment_model_id' => (int) $equipment->equipment_model_id,
                'sales_order_id' => (int) $allocatedItem->sales_order_id,
                'sales_order_item_id' => (int) $allocatedItem->id,
                'user_id' => $userId,
                'barcode_input' => $barcodeInput,
                'barcode_normalized' => $normalizedBarcode,
                'device_identifier' => $this->normalizeNullableText($deviceIdentifier),
                'notes' => $this->normalizeNullableText($notes),
                'scanned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'result' => 'updated',
                'serial_number' => (string) $equipment->serial_number,
                'message' => 'Equipamento baixado com sucesso na montagem.',
                'order_code' => (string) $allocatedItem->codigo_pedido,
                'model_name' => (string) $equipment->model_name,
            ];
        });
    }

    /**
     * @return array{
     *     by_id: array<int, object>,
     *     model_keys: array<int, array<int, string>>,
     *     candidate_index: array<string, array<int, int>>
     * }
     */
    private function loadModelIndex(int $tenantId): array
    {
        $models = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category']);

        $byId = [];
        $modelKeys = [];
        $candidateIndex = [];

        foreach ($models as $model) {
            $byId[(int) $model->id] = $model;
            $candidates = $this->buildModelCandidates(
                (string) $model->name,
                (string) ($model->code ?? ''),
                (string) ($model->category ?? '')
            );

            $modelKeys[(int) $model->id] = $candidates;

            foreach ($candidates as $candidate) {
                $candidateIndex[$candidate] ??= [];
                $candidateIndex[$candidate][] = (int) $model->id;
            }
        }

        return [
            'by_id' => $byId,
            'model_keys' => $modelKeys,
            'candidate_index' => $candidateIndex,
        ];
    }

    /**
     * @param  array{
     *     by_id: array<int, object>,
     *     model_keys: array<int, array<int, string>>,
     *     candidate_index: array<string, array<int, int>>
     * }  $modelIndex
     */
    private function resolveModelForOrderItem(object $item, array $modelIndex): ?object
    {
        $candidateKeys = [];
        foreach ([
            $item->nomus_product_code ?? null,
            $item->nomus_product_name ?? null,
            $item->nomus_product_description ?? null,
            $item->product_code ?? null,
            $item->product_name ?? null,
        ] as $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            foreach ($this->buildModelCandidates($text) as $candidate) {
                $candidateKeys[$candidate] = true;
            }
        }

        if ($candidateKeys === []) {
            return null;
        }

        $exactMatches = [];
        foreach (array_keys($candidateKeys) as $candidateKey) {
            $ids = $modelIndex['candidate_index'][$candidateKey] ?? [];
            foreach ($ids as $id) {
                $exactMatches[$id] = true;
            }
        }

        if (count($exactMatches) === 1) {
            $modelId = (int) array_key_first($exactMatches);

            return $modelIndex['by_id'][$modelId] ?? null;
        }

        $bestModelId = null;
        $bestScore = 0;
        $isTie = false;
        foreach ($modelIndex['model_keys'] as $modelId => $modelKeys) {
            $score = 0;

            foreach ($modelKeys as $modelKey) {
                if (strlen($modelKey) < 2) {
                    continue;
                }

                foreach (array_keys($candidateKeys) as $candidateKey) {
                    if (strlen($candidateKey) < 2) {
                        continue;
                    }

                    if (str_contains($candidateKey, $modelKey) || str_contains($modelKey, $candidateKey)) {
                        $score = max($score, min(strlen($modelKey), strlen($candidateKey)));
                    }
                }
            }

            if ($score <= 0) {
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestModelId = (int) $modelId;
                $isTie = false;
            } elseif ($score === $bestScore) {
                $isTie = true;
            }
        }

        if ($bestModelId === null || $isTie) {
            return null;
        }

        return $modelIndex['by_id'][$bestModelId] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function buildModelCandidates(string ...$texts): array
    {
        $candidates = [];
        foreach ($texts as $text) {
            $raw = trim($text);
            if ($raw === '') {
                continue;
            }

            $normalized = $this->normalizeText($raw);
            if ($normalized !== '') {
                $candidates[$normalized] = true;
            }

            $asciiUpper = Str::upper(Str::ascii($raw));
            $tokens = preg_split('/[^A-Z0-9]+/', $asciiUpper) ?: [];
            $tokens = array_values(array_filter($tokens, static fn ($token): bool => $token !== ''));

            foreach ($tokens as $token) {
                if (preg_match('/^V[0-9]+[A-Z]*$/', $token) === 1) {
                    $candidates[$token] = true;
                }
            }

            if (count($tokens) >= 2) {
                $first = $tokens[0];
                $last = $tokens[count($tokens) - 1];

                if (preg_match('/^V[0-9]+$/', $first) === 1 && preg_match('/^[A-Z]$/', $last) === 1) {
                    $candidates[$first.$last] = true;
                }

                if (preg_match('/^V[0-9]+$/', $first) === 1 && preg_match('/^[A-Z]{2,}$/', $tokens[1]) === 1) {
                    $candidates[$first.$tokens[1]] = true;
                }
            }
        }

        return array_values(array_keys($candidates));
    }

    private function normalizeText(string $value): string
    {
        $normalized = Str::upper(Str::ascii(trim($value)));
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';

        return $normalized;
    }

    private function normalizeScannerCode(string $value): string
    {
        $normalized = Str::upper(trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized;
    }

    private function normalizeNullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function parseDecimal(string $value): float
    {
        $raw = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return 0.0;
        }

        return round((float) $raw, 4);
    }

    private function formatQuantity(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, ',', '.');
    }

    private function parseDateOrNull(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd/m/Y H:i:s'];
        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value, config('app.timezone', 'America/Sao_Paulo'));
            } catch (\Throwable) {
                // continue
            }
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone', 'America/Sao_Paulo'));
        } catch (\Throwable) {
            return null;
        }
    }
}
