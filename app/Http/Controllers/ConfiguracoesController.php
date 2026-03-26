<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfiguracoesController extends Controller
{
    /** Modules available for granular permissions. */
    private const MODULES = [
        'equipamentos' => 'Equipamentos',
        'entrada' => 'Entrada',
        'carregamentos' => 'Carregamentos',
        'notas_fiscais' => 'Notas Fiscais',
        'historicos' => 'Historicos',
        'montagem' => 'Montagem',
        'modelos' => 'Modelos de Equipamento',
    ];

    public function index(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $currentUser = auth()->user();
        $currentRole = UserRole::tryFrom($currentUser->role ?? 'operator') ?? UserRole::Operator;
        abort_unless($currentRole->canManageUsers(), 403);

        $tab = $request->query('tab', 'usuarios');

        $users = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username', 'role', 'created_at']);

        $availableRoles = $this->getAssignableRoles($currentRole);

        // Dados da empresa
        $tenantData = DB::table('tenants')->where('id', $tenant->id)->first();

        // Assinatura
        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        // Integrações
        $integrations = DB::table('integrations')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_native')
            ->orderBy('name')
            ->get();

        return view('configuracoes.index', [
            'tab' => $tab,
            'users' => $users,
            'modules' => self::MODULES,
            'availableRoles' => $availableRoles,
            'currentRole' => $currentRole,
            'tenantData' => $tenantData,
            'integrations' => $integrations,
            'subscription' => $subscription,
        ]);
    }

    public function updateCompany(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'razao_social' => ['nullable', 'string', 'max:200'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'endereco' => ['nullable', 'string', 'max:300'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:2'],
            'cep' => ['nullable', 'string', 'max:10'],
        ]);

        DB::table('tenants')->where('id', $tenant->id)->update([
            'name' => trim($validated['name']),
            'cnpj' => $validated['cnpj'] ?? null,
            'razao_social' => $validated['razao_social'] ?? null,
            'telefone' => $validated['telefone'] ?? null,
            'email' => $validated['email'] ?? null,
            'endereco' => $validated['endereco'] ?? null,
            'cidade' => $validated['cidade'] ?? null,
            'estado' => $validated['estado'] ?? null,
            'cep' => $validated['cep'] ?? null,
            'updated_at' => now(),
        ]);

        return redirect()->route('configuracoes.index', ['tab' => 'empresa'])->with('swal', [
            'icon' => 'success',
            'title' => 'Salvo',
            'text' => 'Dados da empresa atualizados.',
        ]);
    }

    public function storeUser(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $currentRole = $this->getCurrentRole();
        $assignableRoles = array_keys($this->getAssignableRoles($currentRole));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'username' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
        ]);

        $userId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'username' => isset($validated['username']) ? trim($validated['username']) : null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set default permissions based on role
        $this->setDefaultPermissions($userId, UserRole::from($validated['role']));

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Usuario criado',
            'text' => trim($validated['name']) . ' foi cadastrado com sucesso.',
        ]);
    }

    public function updateUser(Request $request, int $user, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $currentRole = $this->getCurrentRole();
        $assignableRoles = array_keys($this->getAssignableRoles($currentRole));

        $row = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->where('id', $user)
            ->first(['id', 'role']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuario nao encontrado.']);
        }

        // Can't edit a user with higher or equal role (unless CEO)
        $targetRole = UserRole::tryFrom($row->role) ?? UserRole::Operator;
        if (! $currentRole->canManageRoles() && $targetRole->atLeast($currentRole)) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Voce nao pode editar este usuario.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['nullable', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
        ]);

        $update = [
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'username' => isset($validated['username']) ? trim($validated['username']) : null,
            'role' => $validated['role'],
            'updated_at' => now(),
        ];

        if (! empty($validated['password'])) {
            $update['password'] = Hash::make($validated['password']);
        }

        DB::table('users')->where('id', $user)->update($update);

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Atualizado',
            'text' => 'Usuario atualizado com sucesso.',
        ]);
    }

    public function destroyUser(int $user, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $row = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->where('id', $user)
            ->first(['id', 'name', 'role']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuario nao encontrado.']);
        }

        // Can't delete yourself
        if ((int) $row->id === (int) auth()->id()) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Voce nao pode remover a si mesmo.']);
        }

        // Can't delete CEO
        if ($row->role === 'ceo') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Nao e possivel remover o CEO.']);
        }

        DB::table('user_permissions')->where('user_id', $row->id)->delete();
        DB::table('users')->where('id', $row->id)->delete();

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Removido',
            'text' => $row->name . ' foi removido.',
        ]);
    }

    public function updatePermissions(Request $request, int $user, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $row = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->where('id', $user)
            ->first(['id', 'role']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuario nao encontrado.']);
        }

        $now = now();
        foreach (self::MODULES as $moduleKey => $moduleLabel) {
            $perms = $request->input('perm_' . $moduleKey, []);

            DB::table('user_permissions')->updateOrInsert(
                ['user_id' => $row->id, 'module' => $moduleKey],
                [
                    'can_view' => in_array('view', $perms, true),
                    'can_create' => in_array('create', $perms, true),
                    'can_edit' => in_array('edit', $perms, true),
                    'can_delete' => in_array('delete', $perms, true),
                    'updated_at' => $now,
                ]
            );
        }

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Permissoes salvas',
            'text' => 'Permissoes atualizadas com sucesso.',
        ]);
    }

    private function authorizeManageUsers(): void
    {
        $role = $this->getCurrentRole();
        abort_unless($role->canManageUsers(), 403);
    }

    private function getCurrentRole(): UserRole
    {
        return UserRole::tryFrom(auth()->user()->role ?? 'operator') ?? UserRole::Operator;
    }

    /**
     * @return array<string, string>
     */
    private function getAssignableRoles(UserRole $currentRole): array
    {
        $roles = [
            'operator' => 'Operador',
            'supervisor' => 'Supervisor',
        ];

        if ($currentRole->atLeast(UserRole::Ceo)) {
            $roles['admin'] = 'Administrador';
            $roles['ceo'] = 'CEO';
        } elseif ($currentRole->atLeast(UserRole::Admin)) {
            $roles['admin'] = 'Administrador';
        }

        return $roles;
    }

    // ═══════════════ INTEGRAÇÕES ═══════════════

    public function storeIntegration(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'string', Rule::in(['erp', 'api', 'webhook'])],
            'base_url' => ['required', 'url', 'max:500'],
            'auth_type' => ['required', 'string', Rule::in(['basic', 'bearer', 'api_key', 'none'])],
            'auth_value' => ['nullable', 'string', 'max:1000'],
            'verify_ssl' => ['nullable', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $slug = Str::slug($validated['name']);
        $counter = 2;
        $baseSlug = $slug;
        while (DB::table('integrations')->where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        DB::table('integrations')->insert([
            'tenant_id' => $tenant->id,
            'slug' => $slug,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'base_url' => rtrim(trim($validated['base_url']), '/'),
            'auth_type' => $validated['auth_type'],
            'auth_value' => $validated['auth_value'] ?? null,
            'verify_ssl' => (bool) ($validated['verify_ssl'] ?? true),
            'timeout_seconds' => (int) ($validated['timeout_seconds'] ?? 30),
            'status' => 'disconnected',
            'is_native' => false,
            'webhook_config' => $validated['type'] === 'webhook' ? json_encode($this->parseWebhookEvents($request)) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('configuracoes.index', ['tab' => 'integracoes'])->with('swal', [
            'icon' => 'success',
            'title' => 'Integração criada',
            'text' => trim($validated['name']) . ' adicionada com sucesso.',
        ]);
    }

    public function updateIntegration(Request $request, int $integration, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $row = DB::table('integrations')
            ->where('tenant_id', $tenant->id)
            ->where('id', $integration)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Integração não encontrada.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_url' => ['required', 'url', 'max:500'],
            'auth_type' => ['required', 'string', Rule::in(['basic', 'bearer', 'api_key', 'none'])],
            'auth_value' => ['nullable', 'string', 'max:1000'],
            'verify_ssl' => ['nullable', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        DB::table('integrations')->where('id', $row->id)->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'base_url' => rtrim(trim($validated['base_url']), '/'),
            'auth_type' => $validated['auth_type'],
            'auth_value' => $validated['auth_value'] ?? null,
            'verify_ssl' => (bool) ($validated['verify_ssl'] ?? true),
            'timeout_seconds' => (int) ($validated['timeout_seconds'] ?? 30),
            'webhook_config' => $request->input('type', 'api') === 'webhook' ? json_encode($this->parseWebhookEvents($request)) : null,
            'updated_at' => now(),
        ]);

        return redirect()->route('configuracoes.index', ['tab' => 'integracoes'])->with('swal', [
            'icon' => 'success',
            'title' => 'Atualizado',
            'text' => 'Integração atualizada.',
        ]);
    }

    public function destroyIntegration(int $integration, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $row = DB::table('integrations')
            ->where('tenant_id', $tenant->id)
            ->where('id', $integration)
            ->first(['id', 'name', 'is_native']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Integração não encontrada.']);
        }

        if ($row->is_native) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Integrações nativas não podem ser removidas.']);
        }

        DB::table('integrations')->where('id', $row->id)->delete();

        return redirect()->route('configuracoes.index', ['tab' => 'integracoes'])->with('swal', [
            'icon' => 'success',
            'title' => 'Removida',
            'text' => $row->name . ' foi removida.',
        ]);
    }

    public function updateWebhookConfig(Request $request, int $integration, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $this->authorizeManageUsers();

        $row = DB::table('integrations')
            ->where('tenant_id', $tenant->id)
            ->where('id', $integration)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Integração não encontrada.']);
        }

        $events = $request->input('events', []);
        $config = [];

        $validEvents = array_keys(\App\Support\Webhooks\WebhookDispatcher::EVENTS);

        foreach ($validEvents as $eventKey) {
            $eventInput = $events[$eventKey] ?? [];
            $enabled = isset($eventInput['enabled']) && $eventInput['enabled'] === '1';
            $fields = isset($eventInput['fields']) && is_array($eventInput['fields']) ? $eventInput['fields'] : [];

            // Only save enabled events
            $config[$eventKey] = [
                'enabled' => $enabled,
                'fields' => $enabled ? array_values($fields) : [],
            ];
        }

        DB::table('integrations')->where('id', $row->id)->update([
            'webhook_config' => json_encode($config),
            'updated_at' => now(),
        ]);

        return redirect()->route('configuracoes.index', ['tab' => 'integracoes'])->with('swal', [
            'icon' => 'success',
            'title' => 'Salvo',
            'text' => 'Configuração de eventos atualizada.',
        ]);
    }

    public function testIntegration(int $integration, TenantContext $tenantContext): JsonResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $row = DB::table('integrations')
            ->where('tenant_id', $tenant->id)
            ->where('id', $integration)
            ->first();

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Integração não encontrada.']);
        }

        try {
            $client = Http::timeout((int) $row->timeout_seconds)
                ->withOptions(['verify' => (bool) $row->verify_ssl])
                ->acceptJson();

            if ($row->auth_type === 'basic') {
                $authHeader = str_starts_with(strtolower((string) $row->auth_value), 'basic ')
                    ? (string) $row->auth_value
                    : 'Basic ' . (string) $row->auth_value;
                $client = $client->withHeaders(['Authorization' => $authHeader]);
            } elseif ($row->auth_type === 'bearer') {
                $client = $client->withToken((string) $row->auth_value);
            } elseif ($row->auth_type === 'api_key') {
                $client = $client->withHeaders(['X-API-Key' => (string) $row->auth_value]);
            }

            $url = rtrim((string) $row->base_url, '/');
            $response = $row->type === 'webhook'
                ? $client->post($url, ['test' => true])
                : $client->get($url);

            $now = now();
            if ($response->successful()) {
                DB::table('integrations')->where('id', $row->id)->update([
                    'status' => 'connected',
                    'last_tested_at' => $now,
                    'last_test_result' => 'OK — HTTP ' . $response->status(),
                    'updated_at' => $now,
                ]);

                return response()->json(['ok' => true, 'message' => 'Conexão OK — HTTP ' . $response->status()]);
            }

            DB::table('integrations')->where('id', $row->id)->update([
                'status' => 'error',
                'last_tested_at' => $now,
                'last_test_result' => 'HTTP ' . $response->status(),
                'updated_at' => $now,
            ]);

            return response()->json(['ok' => false, 'message' => 'Falha — HTTP ' . $response->status()]);
        } catch (\Throwable $e) {
            $now = now();
            DB::table('integrations')->where('id', $row->id)->update([
                'status' => 'error',
                'last_tested_at' => $now,
                'last_test_result' => mb_substr($e->getMessage(), 0, 400),
                'updated_at' => $now,
            ]);

            return response()->json(['ok' => false, 'message' => 'Erro: ' . mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    private function parseWebhookEvents(Request $request): array
    {
        $events = $request->input('events', []);
        $config = [];
        $validEvents = array_keys(\App\Support\Webhooks\WebhookDispatcher::EVENTS);

        foreach ($validEvents as $eventKey) {
            $eventInput = $events[$eventKey] ?? [];
            $enabled = isset($eventInput['enabled']) && $eventInput['enabled'] === '1';
            $fields = isset($eventInput['fields']) && is_array($eventInput['fields']) ? $eventInput['fields'] : [];
            $config[$eventKey] = [
                'enabled' => $enabled,
                'fields' => $enabled ? array_values($fields) : [],
            ];
        }

        return $config;
    }

    private function setDefaultPermissions(int $userId, UserRole $role): void
    {
        $now = now();
        foreach (self::MODULES as $moduleKey => $moduleLabel) {
            $canAll = in_array($role, [UserRole::Ceo, UserRole::Admin], true);
            $canView = $canAll || $role === UserRole::Supervisor;

            DB::table('user_permissions')->insert([
                'user_id' => $userId,
                'module' => $moduleKey,
                'can_view' => $canView || $role === UserRole::Operator,
                'can_create' => $canAll,
                'can_edit' => $canAll,
                'can_delete' => $canAll,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
