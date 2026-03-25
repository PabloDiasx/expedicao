<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
        ]);

        $defaultTenantId = Tenant::query()
            ->where('slug', config('tenancy.default_slug', 'liveequipamentos'))
            ->value('id');

        User::query()->updateOrCreate(
            ['email' => 'admin@livepilates.local'],
            [
                'tenant_id' => $defaultTenantId,
                'role' => 'admin',
                'name' => 'Administrador Live Pilates',
                'username' => 'admin',
                'password' => 'admin123',
                'email_verified_at' => Carbon::now(),
            ]
        );

        $this->call([
            SectorSeeder::class,
            StatusSeeder::class,
            DemoEquipmentSeeder::class,
        ]);
    }
}
