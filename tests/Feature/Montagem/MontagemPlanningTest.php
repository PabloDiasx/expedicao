<?php

namespace Tests\Feature\Montagem;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Operations\MontagemPlanningService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MontagemPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregates_only_pd_orders_with_pending_status(): void
    {
        $tenant = $this->createTenant();
        $this->seedBaseSetup($tenant->id);
        $modelId = $this->createModel($tenant->id, 'V12 LIVE');

        DB::table('nomus_products')->insert([
            'tenant_id' => $tenant->id,
            'external_id' => 1001,
            'codigo' => 'V12 LIVE',
            'nome' => 'V12 LIVE',
            'descricao' => 'APARELHO V12 LIVE',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderPd = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9001,
            'codigo_pedido' => 'PD.9001',
            'data_emissao' => '2026-03-01',
            'data_entrega_padrao' => '2026-03-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nomus_sales_order_items')->insert([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $orderPd,
            'item_code' => '1',
            'product_external_id' => 1001,
            'quantity' => 2,
            'allocated_quantity' => 0,
            'item_status' => 1,
            'delivery_date' => '2026-03-25',
            'product_code' => 'V12 LIVE',
            'product_name' => 'V12 LIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderNotPending = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9002,
            'codigo_pedido' => 'PD.9002',
            'data_emissao' => '2026-03-01',
            'data_entrega_padrao' => '2026-03-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nomus_sales_order_items')->insert([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $orderNotPending,
            'item_code' => '1',
            'product_external_id' => 1001,
            'quantity' => 3,
            'allocated_quantity' => 0,
            'item_status' => 2,
            'delivery_date' => '2026-03-25',
            'product_code' => 'V12 LIVE',
            'product_name' => 'V12 LIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderNotPd = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9003,
            'codigo_pedido' => 'PA.9003',
            'data_emissao' => '2026-03-01',
            'data_entrega_padrao' => '2026-03-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nomus_sales_order_items')->insert([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $orderNotPd,
            'item_code' => '1',
            'product_external_id' => 1001,
            'quantity' => 4,
            'allocated_quantity' => 0,
            'item_status' => 1,
            'delivery_date' => '2026-03-25',
            'product_code' => 'V12 LIVE',
            'product_name' => 'V12 LIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var MontagemPlanningService $service */
        $service = app(MontagemPlanningService::class);
        $dashboard = $service->buildDashboard(
            tenantId: (int) $tenant->id,
            dueFrom: CarbonImmutable::parse('2026-03-01'),
            dueUntil: CarbonImmutable::parse('2026-03-30'),
            search: null
        );

        $this->assertCount(1, $dashboard['equipment_rows']);
        $this->assertSame('V12 LIVE', $dashboard['equipment_rows'][0]['model_name']);
        $this->assertSame('2', $dashboard['equipment_rows'][0]['ordered']);
        $this->assertSame('0', $dashboard['equipment_rows'][0]['assembled']);
        $this->assertSame('2', $dashboard['equipment_rows'][0]['remaining']);
        $this->assertSame((int) $modelId, $dashboard['equipment_rows'][0]['model_id']);
    }

    public function test_scan_is_allocated_by_fifo_delivery_date(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant->id);
        $statusIds = $this->seedBaseSetup($tenant->id);
        $modelId = $this->createModel($tenant->id, 'V8 CADILLAC X');

        DB::table('nomus_products')->insert([
            [
                'tenant_id' => $tenant->id,
                'external_id' => 2001,
                'codigo' => 'V8 CADILLAC X',
                'nome' => 'V8 CADILLAC X',
                'descricao' => 'APARELHO V8 CADILLAC X',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'external_id' => 2002,
                'codigo' => 'V8 CADILLAC X',
                'nome' => 'V8 CADILLAC X',
                'descricao' => 'APARELHO V8 CADILLAC X',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $earlierOrder = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9101,
            'codigo_pedido' => 'PD.9101',
            'data_entrega_padrao' => '2026-04-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $laterOrder = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9102,
            'codigo_pedido' => 'PD.9102',
            'data_entrega_padrao' => '2026-04-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nomus_sales_order_items')->insert([
            [
                'tenant_id' => $tenant->id,
                'sales_order_id' => $earlierOrder,
                'item_code' => '1',
                'product_external_id' => 2001,
                'quantity' => 1,
                'allocated_quantity' => 0,
                'item_status' => 1,
                'delivery_date' => '2026-04-05',
                'product_code' => 'V8 CADILLAC X',
                'product_name' => 'V8 CADILLAC X',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'sales_order_id' => $laterOrder,
                'item_code' => '1',
                'product_external_id' => 2002,
                'quantity' => 1,
                'allocated_quantity' => 0,
                'item_status' => 1,
                'delivery_date' => '2026-04-10',
                'product_code' => 'V8 CADILLAC X',
                'product_name' => 'V8 CADILLAC X',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('equipments')->insert([
            [
                'tenant_id' => $tenant->id,
                'equipment_model_id' => $modelId,
                'serial_number' => 'V8.26.1001',
                'barcode' => 'BC-V8-1001',
                'current_status_id' => $statusIds['produzindo'],
                'current_sector_id' => $statusIds['montagem_sector_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'equipment_model_id' => $modelId,
                'serial_number' => 'V8.26.1002',
                'barcode' => 'BC-V8-1002',
                'current_status_id' => $statusIds['produzindo'],
                'current_sector_id' => $statusIds['montagem_sector_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /** @var MontagemPlanningService $service */
        $service = app(MontagemPlanningService::class);

        $first = $service->registerScan(
            tenantId: (int) $tenant->id,
            userId: (int) $user->id,
            barcode: 'BC-V8-1001',
            deviceIdentifier: 'COLETOR-1',
            notes: null
        );
        $second = $service->registerScan(
            tenantId: (int) $tenant->id,
            userId: (int) $user->id,
            barcode: 'BC-V8-1002',
            deviceIdentifier: 'COLETOR-1',
            notes: null
        );

        $this->assertSame('updated', $first['result']);
        $this->assertSame('updated', $second['result']);
        $this->assertSame('PD.9101', $first['order_code']);
        $this->assertSame('PD.9102', $second['order_code']);
    }

    public function test_duplicate_scan_is_blocked_and_not_allocated_twice(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant->id);
        $statusIds = $this->seedBaseSetup($tenant->id);
        $modelId = $this->createModel($tenant->id, 'V4 STEP CHAIR');

        DB::table('nomus_products')->insert([
            'tenant_id' => $tenant->id,
            'external_id' => 3001,
            'codigo' => 'V4 STEP CHAIR',
            'nome' => 'V4 STEP CHAIR',
            'descricao' => 'APARELHO V4 STEP CHAIR',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('nomus_sales_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'external_id' => 9201,
            'codigo_pedido' => 'PD.9201',
            'data_entrega_padrao' => '2026-04-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('nomus_sales_order_items')->insertGetId([
            'tenant_id' => $tenant->id,
            'sales_order_id' => $orderId,
            'item_code' => '1',
            'product_external_id' => 3001,
            'quantity' => 2,
            'allocated_quantity' => 0,
            'item_status' => 1,
            'delivery_date' => '2026-04-15',
            'product_code' => 'V4 STEP CHAIR',
            'product_name' => 'V4 STEP CHAIR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('equipments')->insert([
            'tenant_id' => $tenant->id,
            'equipment_model_id' => $modelId,
            'serial_number' => 'V4.26.5001',
            'barcode' => 'BC-V4-5001',
            'current_status_id' => $statusIds['produzindo'],
            'current_sector_id' => $statusIds['montagem_sector_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var MontagemPlanningService $service */
        $service = app(MontagemPlanningService::class);

        $first = $service->registerScan(
            tenantId: (int) $tenant->id,
            userId: (int) $user->id,
            barcode: 'BC-V4-5001',
            deviceIdentifier: 'COLETOR-1',
            notes: null
        );
        $second = $service->registerScan(
            tenantId: (int) $tenant->id,
            userId: (int) $user->id,
            barcode: 'BC-V4-5001',
            deviceIdentifier: 'COLETOR-1',
            notes: null
        );

        $allocated = (float) DB::table('nomus_sales_order_items')
            ->where('id', $itemId)
            ->value('allocated_quantity');

        $this->assertSame('updated', $first['result']);
        $this->assertSame('duplicate_scan', $second['result']);
        $this->assertSame(1.0, $allocated);
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant Teste',
            'slug' => 'tenantteste',
            'is_active' => true,
        ]);
    }

    private function createUser(int $tenantId): User
    {
        return User::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Operador',
            'username' => 'operador',
            'email' => 'operador@tenantteste.local',
            'password' => 'password123',
        ]);
    }

    /**
     * @return array{produzindo:int, montado:int, montagem_sector_id:int}
     */
    private function seedBaseSetup(int $tenantId): array
    {
        DB::table('statuses')->insert([
            [
                'code' => 'produzindo',
                'name' => 'Produzindo',
                'color' => '#2563EB',
                'sort_order' => 10,
                'is_terminal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'montado',
                'name' => 'Montado',
                'color' => '#16A34A',
                'sort_order' => 20,
                'is_terminal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sectors')->insert([
            [
                'code' => 'montagem',
                'name' => 'Montagem',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'producao',
                'name' => 'Producao',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'estoque',
                'name' => 'Estoque',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [
            'produzindo' => (int) DB::table('statuses')->where('code', 'produzindo')->value('id'),
            'montado' => (int) DB::table('statuses')->where('code', 'montado')->value('id'),
            'montagem_sector_id' => (int) DB::table('sectors')->where('code', 'montagem')->value('id'),
        ];
    }

    private function createModel(int $tenantId, string $name): int
    {
        return (int) DB::table('equipment_models')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => Str::upper(str_replace(' ', '_', $name)),
            'name' => $name,
            'category' => null,
            'barcode_prefix' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
