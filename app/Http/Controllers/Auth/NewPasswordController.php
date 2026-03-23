<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token, TenantContext $tenantContext): View
    {
        return view('auth.reset-password', [
            'tenant' => $tenantContext->tenant(),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ], [
            'email.required' => 'Informe o e-mail.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmacao de senha nao confere.',
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
            return back()->withErrors(['email' => 'Usuario nao encontrado para este tenant.']);
        }

        $status = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'] ?? '',
                'token' => $validated['token'],
            ],
            function (User $resetUser, string $password): void {
                $resetUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($resetUser));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Senha redefinida com sucesso.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}

