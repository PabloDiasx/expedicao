<?php

namespace App\Support\Nomus;

use App\Models\NomusProduct;
use App\Models\NomusProductComponent;
use App\Models\NomusSalesOrder;
use App\Models\NomusSalesOrderItem;
use App\Models\NomusSalesSyncState;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NomusSalesSyncService
{
    private const SYNC_KEY_ORDERS = 'sales_orders';

    private const SYNC_KEY_PRODUCTS = 'sales_products';

    private const SYNC_KEY_BOM = 'sales_bom';

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
    public function syncOrdersTenant(Tenant $tenant, bool $fullSync = false): array
    {
        $state = $this->beginSyncState($tenant, self::SYNC_KEY_ORDERS);
        $from = $this->resolveSyncStartingPoint($state, $fullSync);
        $query = 'dataModificacao>'.$from->format('Y-m-d\TH:i:s');
        $now = now();

        $created = 0;
        $updated = 0;
        $processed = 0;
        $pageCount = 0;
        $maxModifiedAt = $state->last_synced_modified_at?->toImmutable();
        $productIds = [];

        try {
            for ($page = 1; ; $page++) {
                $rows = $this->apiClient->listSalesOrders($page, $query);
                $pageCount++;

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $orderData) {
                    $upsert = $this->upsertOrderWithItems((int) $tenant->id, $orderData, $now);
                    if ($upsert['skipped']) {
                        continue;
                    }

                    $created += $upsert['created'];
                    $updated += $upsert['updated'];
                    $processed++;

                    foreach ($upsert['product_ids'] as $productId) {
                        $productIds[(int) $productId] = true;
                    }

                    $modifiedAt = $upsert['nomus_updated_at'];
                    if ($modifiedAt !== null && ($maxModifiedAt === null || $modifiedAt->gt($maxModifiedAt))) {
                        $maxModifiedAt = $modifiedAt;
                    }
                }
            }

            if ($productIds !== []) {
                $this->syncProductsByIds((int) $tenant->id, array_keys($productIds), $now);
                $this->refreshOrderItemProductFields((int) $tenant->id, array_keys($productIds));
            }
        } catch (Throwable $exception) {
            $this->finishSyncStateWithError($state, $exception, $processed, $created, $updated, $pageCount);

            throw $exception;
        }

        $this->finishSyncStateWithSuccess($state, $now, $processed, $created, $updated, $pageCount, $maxModifiedAt);

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
    public function syncProductsTenant(Tenant $tenant, bool $fullSync = false): array
    {
        $state = $this->beginSyncState($tenant, self::SYNC_KEY_PRODUCTS);
        $from = $this->resolveSyncStartingPoint($state, $fullSync);
        $query = 'dataModificacao>'.$from->format('Y-m-d\TH:i:s');
        $now = now();

        $created = 0;
        $updated = 0;
        $processed = 0;
        $pageCount = 0;
        $maxModifiedAt = $state->last_synced_modified_at?->toImmutable();

        try {
            for ($page = 1; ; $page++) {
                $rows = $this->apiClient->listProducts($page, $query);
                $pageCount++;

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $productData) {
                    $upsert = $this->upsertProduct((int) $tenant->id, $productData, $now);
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
            $this->finishSyncStateWithError($state, $exception, $processed, $created, $updated, $pageCount);

            throw $exception;
        }

        $this->finishSyncStateWithSuccess($state, $now, $processed, $created, $updated, $pageCount, $maxModifiedAt);

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
    public function syncBomTenant(Tenant $tenant, bool $fullSync = false): array
    {
        $state = $this->beginSyncState($tenant, self::SYNC_KEY_BOM);
        $from = $this->resolveSyncStartingPoint($state, $fullSync);
        $now = now();

        $created = 0;
        $updated = 0;
        $processed = 0;
        $pageCount = 0;
        $maxModifiedAt = $state->last_synced_modified_at?->toImmutable();

        $pendingStatus = (int) config('services.nomus.pending_item_status', 1);
        $prefix = Str::upper(trim((string) config('services.nomus.sales_order_prefix', 'PD')));
        $prefixLike = $prefix === '' ? '%' : $prefix.'.%';

        $parentProductIds = DB::table('nomus_sales_order_items as soi')
            ->join('nomus_sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->where('soi.tenant_id', $tenant->id)
            ->where('so.codigo_pedido', 'like', $prefixLike)
            ->where('soi.item_status', $pendingStatus)
            ->whereNotNull('soi.product_external_id')
            ->distinct()
            ->pluck('soi.product_external_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        try {
            foreach ($parentProductIds as $parentProductId) {
                $seenExternalIds = [];

                for ($page = 1; ; $page++) {
                    $query = 'produtoPai.id='.$parentProductId;
                    $rows = $this->apiClient->listProductComponents($page, $query);
                    $pageCount++;

                    if ($rows === []) {
                        break;
                    }

                    foreach ($rows as $componentData) {
                        $parentId = (int) ($componentData['produtoPai']['id'] ?? 0);
                        if ($parentId !== $parentProductId) {
                            continue;
                        }

                        $upsert = $this->upsertProductComponent((int) $tenant->id, $componentData, $now);
                        $created += $upsert['created'];
                        $updated += $upsert['updated'];
                        $processed++;
                        $seenExternalIds[] = $upsert['external_id'];

                        $modifiedAt = $upsert['nomus_updated_at'];
                        if ($modifiedAt !== null && ($maxModifiedAt === null || $modifiedAt->gt($maxModifiedAt))) {
                            $maxModifiedAt = $modifiedAt;
                        }
                    }
                }

                DB::table('nomus_product_components')
                    ->where('tenant_id', $tenant->id)
                    ->where('parent_product_external_id', $parentProductId)
                    ->when($seenExternalIds !== [], function ($query) use ($seenExternalIds): void {
                        $query->whereNotIn('external_id', $seenExternalIds);
                    })
                    ->delete();
            }
        } catch (Throwable $exception) {
            $this->finishSyncStateWithError($state, $exception, $processed, $created, $updated, $pageCount);

            throw $exception;
        }

        $this->finishSyncStateWithSuccess($state, $now, $processed, $created, $updated, $pageCount, $maxModifiedAt);

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

    private function beginSyncState(Tenant $tenant, string $syncKey): NomusSalesSyncState
    {
        $state = NomusSalesSyncState::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'sync_key' => $syncKey,
            ],
            [
                'last_items_count' => 0,
                'last_created_count' => 0,
                'last_updated_count' => 0,
                'last_pages_count' => 0,
            ]
        );

        $state->forceFill([
            'last_run_at' => now(),
            'last_error' => null,
        ])->save();

        return $state;
    }

    private function finishSyncStateWithError(
        NomusSalesSyncState $state,
        Throwable $exception,
        int $processed,
        int $created,
        int $updated,
        int $pages
    ): void {
        $state->forceFill([
            'last_error' => mb_substr($exception->getMessage(), 0, 1900),
            'last_items_count' => $processed,
            'last_created_count' => $created,
            'last_updated_count' => $updated,
            'last_pages_count' => $pages,
        ])->save();
    }

    private function finishSyncStateWithSuccess(
        NomusSalesSyncState $state,
        Carbon $now,
        int $processed,
        int $created,
        int $updated,
        int $pages,
        ?CarbonImmutable $maxModifiedAt
    ): void {
        $state->forceFill([
            'last_success_at' => $now,
            'last_items_count' => $processed,
            'last_created_count' => $created,
            'last_updated_count' => $updated,
            'last_pages_count' => $pages,
            'last_error' => null,
            'last_synced_modified_at' => $maxModifiedAt?->toDateTimeString(),
        ])->save();
    }

    private function resolveSyncStartingPoint(NomusSalesSyncState $state, bool $fullSync): CarbonImmutable
    {
        $lookbackDays = max(1, (int) config('services.nomus.initial_lookback_days', 30));
        $overlapMinutes = max(0, (int) config('services.nomus.sync_overlap_minutes', 2));

        if ($fullSync || ! $state->last_synced_modified_at) {
            return now()->subDays($lookbackDays)->toImmutable();
        }

        return $state->last_synced_modified_at->toImmutable()->subMinutes($overlapMinutes);
    }

    /**
     * @param  array<string, mixed>  $orderData
     * @return array{
     *     skipped: bool,
     *     created: int,
     *     updated: int,
     *     nomus_updated_at: CarbonImmutable|null,
     *     product_ids: array<int, int>
     * }
     */
    private function upsertOrderWithItems(int $tenantId, array $orderData, Carbon $syncedAt): array
    {
        $externalId = isset($orderData['id']) ? (int) $orderData['id'] : 0;
        if ($externalId <= 0) {
            throw new RuntimeException('Pedido de venda recebido sem id valido.');
        }

        $codigoPedido = trim((string) ($orderData['codigoPedido'] ?? ''));
        if ($codigoPedido === '') {
            throw new RuntimeException('Pedido de venda recebido sem codigoPedido.');
        }

        $prefix = Str::upper(trim((string) config('services.nomus.sales_order_prefix', 'PD')));
        if ($prefix !== '' && ! Str::startsWith(Str::upper($codigoPedido), $prefix)) {
            return [
                'skipped' => true,
                'created' => 0,
                'updated' => 0,
                'nomus_updated_at' => null,
                'product_ids' => [],
            ];
        }

        $nomusCreatedAt = $this->parseDateTime($orderData['dataCriacao'] ?? null);
        $nomusUpdatedAt = $this->parseDateTime($orderData['dataModificacao'] ?? null);
        $dataEmissao = $this->parseDate($orderData['dataEmissao'] ?? null);
        $dataEntregaPadrao = $this->parseDate($orderData['dataEntregaPadrao'] ?? null);

        $orderAttributes = [
            'codigo_pedido' => $codigoPedido,
            'empresa_external_id' => $this->asNullableInt($orderData['idEmpresa'] ?? null),
            'cliente_external_id' => $this->asNullableInt($orderData['idPessoaCliente'] ?? null),
            'data_emissao' => $dataEmissao,
            'data_entrega_padrao' => $dataEntregaPadrao,
            'nomus_created_at' => $nomusCreatedAt?->toDateTimeString(),
            'nomus_updated_at' => $nomusUpdatedAt?->toDateTimeString(),
            'payload' => $orderData,
            'last_synced_at' => $syncedAt->toDateTimeString(),
        ];

        $itemRows = is_array($orderData['itensPedido'] ?? null)
            ? array_values(array_filter($orderData['itensPedido'], static fn ($item): bool => is_array($item)))
            : [];

        return DB::transaction(function () use (
            $tenantId,
            $externalId,
            $orderAttributes,
            $itemRows,
            $orderData,
            $syncedAt,
            $nomusUpdatedAt
        ): array {
            $created = 0;
            $updated = 0;
            $productIds = [];

            $order = NomusSalesOrder::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $order = NomusSalesOrder::query()->create([
                    'tenant_id' => $tenantId,
                    'external_id' => $externalId,
                    ...$orderAttributes,
                ]);

                $created++;
            } else {
                $order->fill($orderAttributes);
                if ($order->isDirty()) {
                    $order->save();
                    $updated++;
                }
            }

            $seenItemCodes = [];
            foreach ($itemRows as $index => $itemData) {
                $itemCode = trim((string) ($itemData['item'] ?? ''));
                if ($itemCode === '') {
                    $itemCode = (string) ($index + 1);
                }

                $seenItemCodes[] = $itemCode;

                $productExternalId = $this->asNullableInt($itemData['idProduto'] ?? null);
                if ($productExternalId !== null) {
                    $productIds[] = $productExternalId;
                }

                $itemAttributes = [
                    'tenant_id' => $tenantId,
                    'sales_order_id' => (int) $order->id,
                    'item_code' => $itemCode,
                    'product_external_id' => $productExternalId,
                    'quantity' => $this->asDecimal($itemData['quantidade'] ?? null),
                    'item_status' => $this->asNullableInt($itemData['status'] ?? null),
                    'delivery_date' => $this->parseDate($itemData['dataEntrega'] ?? ($orderData['dataEntregaPadrao'] ?? null)),
                    'product_code' => null,
                    'product_name' => null,
                    'payload' => $itemData,
                    'last_synced_at' => $syncedAt->toDateTimeString(),
                ];

                $item = NomusSalesOrderItem::query()
                    ->where('sales_order_id', (int) $order->id)
                    ->where('item_code', $itemCode)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    NomusSalesOrderItem::query()->create($itemAttributes);
                    $created++;
                } else {
                    $item->fill($itemAttributes);
                    if ($item->isDirty()) {
                        $item->save();
                        $updated++;
                    }
                }
            }

            if ($seenItemCodes !== []) {
                DB::table('nomus_sales_order_items')
                    ->where('sales_order_id', (int) $order->id)
                    ->whereNotIn('item_code', $seenItemCodes)
                    ->delete();
            } else {
                DB::table('nomus_sales_order_items')
                    ->where('sales_order_id', (int) $order->id)
                    ->delete();
            }

            return [
                'skipped' => false,
                'created' => $created,
                'updated' => $updated,
                'nomus_updated_at' => $nomusUpdatedAt,
                'product_ids' => array_values(array_unique(array_map('intval', $productIds))),
            ];
        });
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function syncProductsByIds(int $tenantId, array $productIds, Carbon $syncedAt): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return;
        }

        foreach ($productIds as $productId) {
            $productData = $this->fetchProductById((int) $productId);
            if (! is_array($productData)) {
                continue;
            }

            $this->upsertProduct($tenantId, $productData, $syncedAt);
        }
    }

    private function refreshOrderItemProductFields(int $tenantId, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $productsById = DB::table('nomus_products')
            ->where('tenant_id', $tenantId)
            ->whereIn('external_id', $productIds)
            ->get(['external_id', 'codigo', 'nome'])
            ->keyBy('external_id');

        DB::table('nomus_sales_order_items')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_external_id', $productIds)
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($productsById): void {
                foreach ($items as $item) {
                    $product = $productsById->get((int) $item->product_external_id);
                    if (! $product) {
                        continue;
                    }

                    DB::table('nomus_sales_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'product_code' => $product->codigo ?: null,
                            'product_name' => $product->nome ?: null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $productData
     * @return array{created:int, updated:int, nomus_updated_at: CarbonImmutable|null}
     */
    private function upsertProduct(int $tenantId, array $productData, Carbon $syncedAt): array
    {
        $externalId = isset($productData['id']) ? (int) $productData['id'] : 0;
        if ($externalId <= 0) {
            throw new RuntimeException('Produto recebido sem id valido.');
        }

        $nomusUpdatedAt = $this->parseDateTime($productData['dataModificacao'] ?? ($productData['dataHoraUltimaModificacao'] ?? null));

        $attributes = [
            'codigo' => $this->asNullableString($productData['codigo'] ?? null),
            'nome' => $this->asNullableString($productData['nome'] ?? null),
            'descricao' => $this->asNullableString($productData['descricao'] ?? null),
            'nome_tipo_produto' => $this->asNullableString($productData['nomeTipoProduto'] ?? null),
            'ativo' => ((bool) ($productData['ativo'] ?? true)),
            'nomus_updated_at' => $nomusUpdatedAt?->toDateTimeString(),
            'payload' => $productData,
            'last_synced_at' => $syncedAt->toDateTimeString(),
        ];

        return DB::transaction(function () use ($tenantId, $externalId, $attributes, $nomusUpdatedAt): array {
            $product = NomusProduct::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                NomusProduct::query()->create([
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

            $product->fill($attributes);
            if ($product->isDirty()) {
                $product->save();

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

    /**
     * @param  array<string, mixed>  $componentData
     * @return array{
     *     created:int,
     *     updated:int,
     *     external_id:int,
     *     component_product_external_id:int|null,
     *     nomus_updated_at: CarbonImmutable|null
     * }
     */
    private function upsertProductComponent(int $tenantId, array $componentData, Carbon $syncedAt): array
    {
        $externalId = isset($componentData['id']) ? (int) $componentData['id'] : 0;
        if ($externalId <= 0) {
            throw new RuntimeException('Componente de lista de materiais sem id valido.');
        }

        $parentProductId = (int) ($componentData['produtoPai']['id'] ?? 0);
        if ($parentProductId <= 0) {
            throw new RuntimeException('Componente de lista de materiais sem produto pai valido.');
        }

        $componentProductId = $this->asNullableInt($componentData['produtoComponente']['id'] ?? null);
        $nomusUpdatedAt = $this->parseDateTime($componentData['dataModificacao'] ?? null);

        $attributes = [
            'parent_product_external_id' => $parentProductId,
            'component_product_external_id' => $componentProductId,
            'component_codigo' => $this->asNullableString($componentData['produtoComponente']['codigo'] ?? null),
            'component_nome' => $this->asNullableString($componentData['produtoComponente']['descricao'] ?? null),
            'quantity_required' => $this->asDecimal($componentData['qtdeNecessaria'] ?? null),
            'optional' => (bool) ($componentData['opcional'] ?? false),
            'item_de_embarque' => (bool) ($componentData['itemDeEmbarque'] ?? false),
            'natureza_consumo' => $this->asNullableInt($componentData['naturezaConsumo'] ?? null),
            'nomus_updated_at' => $nomusUpdatedAt?->toDateTimeString(),
            'payload' => $componentData,
            'last_synced_at' => $syncedAt->toDateTimeString(),
        ];

        return DB::transaction(function () use (
            $tenantId,
            $externalId,
            $attributes,
            $componentProductId,
            $nomusUpdatedAt
        ): array {
            $component = NomusProductComponent::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (! $component) {
                NomusProductComponent::query()->create([
                    'tenant_id' => $tenantId,
                    'external_id' => $externalId,
                    ...$attributes,
                ]);

                return [
                    'created' => 1,
                    'updated' => 0,
                    'external_id' => $externalId,
                    'component_product_external_id' => $componentProductId,
                    'nomus_updated_at' => $nomusUpdatedAt,
                ];
            }

            $component->fill($attributes);
            if ($component->isDirty()) {
                $component->save();

                return [
                    'created' => 0,
                    'updated' => 1,
                    'external_id' => $externalId,
                    'component_product_external_id' => $componentProductId,
                    'nomus_updated_at' => $nomusUpdatedAt,
                ];
            }

            return [
                'created' => 0,
                'updated' => 0,
                'external_id' => $externalId,
                'component_product_external_id' => $componentProductId,
                'nomus_updated_at' => $nomusUpdatedAt,
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProductById(int $productId): ?array
    {
        try {
            return $this->apiClient->getProduct($productId);
        } catch (Throwable) {
            // fallback below
        }

        try {
            $rows = $this->apiClient->listProducts(1, 'id='.$productId);
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $productId) {
                    return $row;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        $timezone = config('app.timezone', 'America/Sao_Paulo');

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            DATE_ATOM,
        ];

        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $raw, $timezone);
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
            'd/m/Y',
            'd/m/Y H:i:s',
            'Y-m-d',
            DATE_ATOM,
        ];

        foreach ($formats as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $raw, $timezone)->toDateString();
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

    private function asDecimal(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $string = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        if ($string === '') {
            return 0.0;
        }

        if (str_contains($string, ',') && str_contains($string, '.')) {
            $lastComma = strrpos($string, ',');
            $lastDot = strrpos($string, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $string = str_replace('.', '', $string);
                $string = str_replace(',', '.', $string);
            } else {
                $string = str_replace(',', '', $string);
            }
        } elseif (str_contains($string, ',')) {
            $string = str_replace(',', '.', $string);
        }

        if (! is_numeric($string)) {
            return 0.0;
        }

        return round((float) $string, 4);
    }
}
