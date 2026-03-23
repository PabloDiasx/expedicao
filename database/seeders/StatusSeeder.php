<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $canonicalStatuses = [
            [
                'code' => 'produzindo',
                'name' => 'Produzindo',
                'description' => 'Equipamento em processo de producao.',
                'color' => '#2563EB',
                'sort_order' => 10,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'montado',
                'name' => 'Montado',
                'description' => 'Equipamento montado e pronto para a proxima etapa.',
                'color' => '#16A34A',
                'sort_order' => 20,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'transferencia',
                'name' => 'Transferencia',
                'description' => 'Equipamento em transferencia entre setores.',
                'color' => '#0EA5E9',
                'sort_order' => 30,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'desmontado',
                'name' => 'Desmontado',
                'description' => 'Equipamento desmontado para ajuste ou retrabalho.',
                'color' => '#DC2626',
                'sort_order' => 40,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'embalado',
                'name' => 'Embalado',
                'description' => 'Equipamento embalado para expedicao.',
                'color' => '#F59E0B',
                'sort_order' => 50,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'finalizado',
                'name' => 'Finalizado',
                'description' => 'Equipamento finalizado no processo interno.',
                'color' => '#14B8A6',
                'sort_order' => 60,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'liberado',
                'name' => 'Liberado',
                'description' => 'Equipamento liberado para expedicao.',
                'color' => '#22C55E',
                'sort_order' => 70,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'carregando',
                'name' => 'Carregando',
                'description' => 'Equipamento em processo de carregamento.',
                'color' => '#F97316',
                'sort_order' => 80,
                'is_terminal' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'carregado',
                'name' => 'Carregado',
                'description' => 'Equipamento carregado e pronto para envio.',
                'color' => '#0F766E',
                'sort_order' => 90,
                'is_terminal' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('statuses')->upsert(
            $canonicalStatuses,
            ['code'],
            ['name', 'description', 'color', 'sort_order', 'is_terminal', 'updated_at']
        );

        $legacyToNew = [
            'produzido' => 'produzindo',
            'em_estoque' => 'liberado',
            'expedido' => 'carregado',
            'manutencao' => 'desmontado',
        ];

        $statusIds = DB::table('statuses')->pluck('id', 'code');

        foreach ($legacyToNew as $legacyCode => $newCode) {
            $legacyId = $statusIds[$legacyCode] ?? null;
            $newId = $statusIds[$newCode] ?? null;

            if (! $legacyId || ! $newId || $legacyId === $newId) {
                continue;
            }

            DB::table('equipments')
                ->where('current_status_id', $legacyId)
                ->update([
                    'current_status_id' => $newId,
                    'updated_at' => $now,
                ]);

            DB::table('status_histories')
                ->where('to_status_id', $legacyId)
                ->update([
                    'to_status_id' => $newId,
                    'updated_at' => $now,
                ]);

            DB::table('status_histories')
                ->where('from_status_id', $legacyId)
                ->update([
                    'from_status_id' => $newId,
                    'updated_at' => $now,
                ]);
        }

        DB::table('statuses')
            ->whereIn('code', array_keys($legacyToNew))
            ->delete();
    }
}
