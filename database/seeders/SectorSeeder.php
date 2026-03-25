<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $this->seed();
        } catch (\Throwable $e) {
            $this->command?->error('SectorSeeder failed: '.$e->getMessage());
        }
    }

    private function seed(): void
    {
        $now = now();

        DB::table('sectors')->upsert([
            [
                'code' => 'producao',
                'name' => 'Producao',
                'description' => 'Setor responsavel pela fabricacao inicial das pecas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'montagem',
                'name' => 'Montagem',
                'description' => 'Setor responsavel pela montagem dos equipamentos.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'expedicao',
                'name' => 'Expedicao',
                'description' => 'Setor responsavel por separacao, conferencia e envio.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'estoque',
                'name' => 'Estoque',
                'description' => 'Setor responsavel pela guarda e disponibilidade do produto.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['name', 'description', 'is_active', 'updated_at']);
    }
}

