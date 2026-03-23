<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_in_tenant_context(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Teste',
            'slug' => 'tenantteste',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Tenant',
            'username' => 'admin',
            'email' => 'admin@tenantteste.local',
            'password' => 'password123',
        ]);

        $response = $this->post('/login?tenant=tenantteste', [
            'login' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_link_is_generated_for_tenant_user(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Teste',
            'slug' => 'tenantteste',
            'is_active' => true,
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Tenant',
            'username' => 'admin',
            'email' => 'admin@tenantteste.local',
            'password' => 'password123',
        ]);

        $response = $this->post('/esqueci-senha?tenant=tenantteste', [
            'email' => 'admin@tenantteste.local',
        ]);

        $response->assertSessionHas('password_reset_link');
        $response->assertSessionHas('status', 'Link de redefinicao gerado com sucesso.');
    }
}

