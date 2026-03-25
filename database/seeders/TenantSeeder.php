<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $this->seed();
        } catch (\Throwable $e) {
            $this->command?->error('TenantSeeder failed: '.$e->getMessage());
        }
    }

    private function seed(): void
    {
        $now = now();

        DB::table('tenants')->upsert([
            [
                'name' => 'Live Equipamentos',
                'slug' => 'liveequipamentos',
                'domain' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'domain', 'is_active', 'updated_at']);
    }
}

