<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EquipmentModelController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', Rule::in(['all', '1', '0'])],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $activeFilter = (string) ($validated['active'] ?? '1');
        $query = DB::table('equipment_models as em')
            ->where('em.tenant_id', $tenant->id)
            ->select([
                'em.id',
                'em.name',
                'em.category',
                'em.is_active',
                'em.created_at',
            ]);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('em.name', 'like', '%'.$search.'%')
                    ->orWhere('em.category', 'like', '%'.$search.'%');
            });
        }

        if ($activeFilter === '1' || $activeFilter === '0') {
            $query->where('em.is_active', (int) $activeFilter === 1);
        }

        $models = $query
            ->orderByDesc('em.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('equipment-models.index', [
            'models' => $models,
            'filters' => [
                'q' => $search,
                'active' => $activeFilter,
            ],
        ]);
    }

    public function store(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'name' => 'Tenant nao identificado.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Informe o nome do modelo para cadastrar.',
            'name.max' => 'O nome do modelo deve ter no maximo 150 caracteres.',
            'category.max' => 'A categoria deve ter no maximo 100 caracteres.',
            'is_active.boolean' => 'Valor invalido para o campo ativo.',
        ]);

        $generatedCode = $this->generateModelCode(
            tenantId: (int) $tenant->id,
            name: trim((string) $validated['name']),
            category: isset($validated['category']) ? trim((string) $validated['category']) : null
        );

        DB::table('equipment_models')->insert([
            'tenant_id' => $tenant->id,
            'code' => $generatedCode,
            'name' => trim((string) $validated['name']),
            'category' => isset($validated['category']) ? trim((string) $validated['category']) : null,
            'barcode_prefix' => null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('equipment-models.index')
            ->with('status', 'Modelo cadastrado com sucesso.');
    }

    public function update(Request $request, int $model, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'barcode_prefix' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $row = DB::table('equipment_models')
            ->where('tenant_id', $tenant->id)
            ->where('id', $model)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Modelo nao encontrado.']);
        }

        DB::table('equipment_models')
            ->where('id', $row->id)
            ->update([
                'name' => trim($validated['name']),
                'category' => isset($validated['category']) ? trim($validated['category']) : null,
                'barcode_prefix' => isset($validated['barcode_prefix']) ? trim($validated['barcode_prefix']) : null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'updated_at' => now(),
            ]);

        return back()->with('swal', ['icon' => 'success', 'title' => 'Atualizado', 'text' => 'Modelo atualizado com sucesso.']);
    }

    public function destroy(Request $request, int $model, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'name' => 'Tenant nao identificado.',
            ]);
        }

        if (! $request->boolean('delete_confirmation')) {
            return redirect()
                ->route('equipment-models.index')
                ->with('swal', [
                    'icon' => 'warning',
                    'title' => 'Confirmacao necessaria',
                    'text' => 'Confirme a remocao antes de excluir o modelo.',
                ]);
        }

        $equipmentModel = DB::table('equipment_models')
            ->where('tenant_id', $tenant->id)
            ->where('id', $model)
            ->first(['id', 'name']);

        if (! $equipmentModel) {
            return back()->withErrors([
                'name' => 'Modelo nao encontrado.',
            ]);
        }

        DB::table('equipment_models')
            ->where('tenant_id', $tenant->id)
            ->where('id', $equipmentModel->id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('equipment-models.index')
            ->with('status', 'Modelo desativado com sucesso.');
    }

    private function generateModelCode(int $tenantId, string $name, ?string $category): string
    {
        $source = trim($name.' '.($category ?? ''));
        $ascii = Str::upper(Str::ascii($source));
        $base = preg_replace('/[^A-Z0-9]+/', '_', $ascii) ?? '';
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'MODELO';
        }

        $base = Str::substr($base, 0, 80);
        $candidate = $base;
        $suffix = 2;

        while (
            DB::table('equipment_models')
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists()
        ) {
            $suffixText = '_'.$suffix;
            $candidate = Str::substr($base, 0, 80 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $candidate;
    }
}
