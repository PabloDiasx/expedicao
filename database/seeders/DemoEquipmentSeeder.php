<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoEquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $this->seed();
        } catch (\Throwable $e) {
            $this->command?->error('DemoEquipmentSeeder failed: '.$e->getMessage());
        }
    }

    private function seed(): void
    {
        $now = now();

        $tenantId = Tenant::query()
            ->where('slug', config('tenancy.default_slug', 'liveequipamentos'))
            ->value('id');

        if (! $tenantId) {
            $this->command?->warn('Default tenant not found, skipping DemoEquipmentSeeder.');
            return;
        }

        DB::table('equipment_models')->upsert([
            [
                'tenant_id' => $tenantId,
                'code' => 'REFORMER_CLASSIC',
                'name' => 'Reformer Classic',
                'category' => 'Reformer',
                'barcode_prefix' => 'RFC',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'CADILLAC_PRO',
                'name' => 'Cadillac Pro',
                'category' => 'Cadillac',
                'barcode_prefix' => 'CDP',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'CHAIR_EVOLUTION',
                'name' => 'Chair Evolution',
                'category' => 'Chair',
                'barcode_prefix' => 'CHE',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['tenant_id', 'code'], ['name', 'category', 'barcode_prefix', 'is_active', 'updated_at']);

        $modelIds = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'code');

        $statusIds = DB::table('statuses')->pluck('id', 'code');
        $sectorIds = DB::table('sectors')->pluck('id', 'code');
        $adminId = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('email', 'admin@livepilates.local')
            ->value('id');

        DB::table('equipments')->upsert([
            [
                'tenant_id' => $tenantId,
                'equipment_model_id' => $modelIds['REFORMER_CLASSIC'] ?? null,
                'serial_number' => 'RFC-2026-0001',
                'barcode' => '7890000000011',
                'current_status_id' => $statusIds['montado'] ?? null,
                'current_sector_id' => $sectorIds['expedicao'] ?? null,
                'manufactured_at' => now()->subDays(15)->toDateString(),
                'assembled_at' => now()->subDays(7)->toDateString(),
                'notes' => 'Lote inicial para validacao interna.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'equipment_model_id' => $modelIds['CADILLAC_PRO'] ?? null,
                'serial_number' => 'CDP-2026-0002',
                'barcode' => '7890000000028',
                'current_status_id' => $statusIds['produzindo'] ?? null,
                'current_sector_id' => $sectorIds['montagem'] ?? null,
                'manufactured_at' => now()->subDays(5)->toDateString(),
                'assembled_at' => null,
                'notes' => 'Aguardando conclusao de montagem.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'equipment_model_id' => $modelIds['CHAIR_EVOLUTION'] ?? null,
                'serial_number' => 'CHE-2026-0003',
                'barcode' => '7890000000035',
                'current_status_id' => $statusIds['liberado'] ?? null,
                'current_sector_id' => $sectorIds['estoque'] ?? null,
                'manufactured_at' => now()->subDays(20)->toDateString(),
                'assembled_at' => now()->subDays(14)->toDateString(),
                'notes' => 'Equipamento liberado para expedicao.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['tenant_id', 'serial_number'], [
            'equipment_model_id',
            'barcode',
            'current_status_id',
            'current_sector_id',
            'manufactured_at',
            'assembled_at',
            'notes',
            'updated_at',
        ]);

        $equipmentIds = DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'serial_number');

        $trackedEquipmentIds = [
            $equipmentIds['RFC-2026-0001'] ?? null,
            $equipmentIds['CDP-2026-0002'] ?? null,
            $equipmentIds['CHE-2026-0003'] ?? null,
        ];
        $trackedEquipmentIds = array_values(array_filter($trackedEquipmentIds));

        if ($trackedEquipmentIds !== []) {
            DB::table('status_histories')
                ->where('tenant_id', $tenantId)
                ->whereIn('equipment_id', $trackedEquipmentIds)
                ->delete();

            DB::table('barcode_reads')
                ->where('tenant_id', $tenantId)
                ->whereIn('equipment_id', $trackedEquipmentIds)
                ->delete();
        }

        DB::table('status_histories')->insert([
            [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentIds['RFC-2026-0001'] ?? null,
                'from_status_id' => $statusIds['produzindo'] ?? null,
                'to_status_id' => $statusIds['montado'] ?? null,
                'sector_id' => $sectorIds['montagem'] ?? null,
                'user_id' => $adminId,
                'event_source' => 'scanner',
                'notes' => 'Mudanca confirmada por leitura de codigo de barras.',
                'changed_at' => now()->subDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentIds['CDP-2026-0002'] ?? null,
                'from_status_id' => null,
                'to_status_id' => $statusIds['produzindo'] ?? null,
                'sector_id' => $sectorIds['producao'] ?? null,
                'user_id' => $adminId,
                'event_source' => 'manual',
                'notes' => 'Registro inicial de producao.',
                'changed_at' => now()->subDays(5),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentIds['CHE-2026-0003'] ?? null,
                'from_status_id' => $statusIds['embalado'] ?? null,
                'to_status_id' => $statusIds['liberado'] ?? null,
                'sector_id' => $sectorIds['estoque'] ?? null,
                'user_id' => $adminId,
                'event_source' => 'manual',
                'notes' => 'Lote finalizado e liberado para expedicao.',
                'changed_at' => now()->subDays(14),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('barcode_reads')->insert([
            [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentIds['RFC-2026-0001'] ?? null,
                'barcode_value' => '7890000000011',
                'sector_id' => $sectorIds['expedicao'] ?? null,
                'user_id' => $adminId,
                'device_identifier' => 'COLETOR-EXP-01',
                'read_result' => 'matched',
                'payload' => json_encode(['type' => 'EAN13', 'quality' => 'A']),
                'read_at' => now()->subDays(1),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipmentIds['CDP-2026-0002'] ?? null,
                'barcode_value' => '7890000000028',
                'sector_id' => $sectorIds['montagem'] ?? null,
                'user_id' => $adminId,
                'device_identifier' => 'COLETOR-MTG-02',
                'read_result' => 'matched',
                'payload' => json_encode(['type' => 'EAN13', 'quality' => 'B']),
                'read_at' => now()->subHours(12),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
