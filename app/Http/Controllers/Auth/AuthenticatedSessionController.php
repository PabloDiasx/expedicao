<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(TenantContext $tenantContext): View
    {
        return view('auth.login', [
            'tenant' => $tenantContext->tenant(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $loginInput = trim((string) $request->input('login', ''));
        $passwordInput = (string) $request->input('password', '');

        if ($loginInput === '' || $passwordInput === '') {
            throw ValidationException::withMessages([
                'login' => 'Informe seu usuario/e-mail e sua senha.',
            ]);
        }

        $validated = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            throw ValidationException::withMessages([
                'login' => 'Tenant nao identificado. Tente acessar com ?tenant=liveequipamentos.',
            ]);
        }

        $login = trim($validated['login']);

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($login): void {
                $query->where('email', $login)
                    ->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Credenciais invalidas para este tenant.',
            ]);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
