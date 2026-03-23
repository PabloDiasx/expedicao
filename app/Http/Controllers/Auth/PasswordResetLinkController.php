<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(TenantContext $tenantContext): View
    {
        return view('auth.forgot-password', [
            'tenant' => $tenantContext->tenant(),
        ]);
    }

    public function store(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail valido.',
        ]);

        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors(['email' => 'Tenant nao identificado.']);
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if (! $user) {
            return back()->with('status', 'Se o e-mail existir, enviaremos o link de redefinicao.');
        }

        $token = Password::broker()->createToken($user);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
            config('tenancy.query_parameter', 'tenant') => $tenant->slug,
        ]);

        return back()
            ->with('status', 'Link de redefinicao gerado com sucesso.')
            ->with('password_reset_link', $resetUrl);
    }
}

