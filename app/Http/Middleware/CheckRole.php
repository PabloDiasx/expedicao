<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $minimum = UserRole::tryFrom($minimumRole);
        if (! $minimum) {
            abort(500, 'Invalid role configuration.');
        }

        $userRole = UserRole::tryFrom($user->role ?? 'operator') ?? UserRole::Operator;

        if (! $userRole->atLeast($minimum)) {
            abort(403, 'Acesso nao autorizado para este recurso.');
        }

        return $next($request);
    }
}
