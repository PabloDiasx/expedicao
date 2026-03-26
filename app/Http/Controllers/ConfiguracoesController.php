<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        return view('configuracoes.index', [
            'tab' => $tab,
            'users' => $users,
            'modules' => self::MODULES,
            'availableRoles' => $availableRoles,
            'currentRole' => $currentRole,
            'tenantData' => $tenantData,
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
