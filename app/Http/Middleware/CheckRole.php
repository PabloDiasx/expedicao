<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Auth\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Map route name prefixes to permission modules.
     */
    private const ROUTE_MODULE_MAP = [
        'equipments.' => 'equipamentos',
        'invoices.' => 'notas_fiscais',
        'carregamentos.' => 'carregamentos',
        'historicos.' => 'historicos',
        'equipment-models.' => 'modelos',
        'expedition.' => 'entrada',
        'production.' => 'montagem',
    ];

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

        // Role is high enough — allow
        if ($userRole->atLeast($minimum)) {
            return $next($request);
        }

        // Role is below minimum — check individual permissions
        $module = $this->resolveModule($request);
        if ($module) {
            $action = $this->resolveAction($request);
            if (PermissionChecker::can($user->id, $module, $action)) {
                return $next($request);
            }
        }

        abort(403, 'Acesso nao autorizado para este recurso.');
    }

    private function resolveAction(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'edit',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    private function resolveModule(Request $request): ?string
    {
        $routeName = $request->route()?->getName() ?? '';

        foreach (self::ROUTE_MODULE_MAP as $prefix => $module) {
            if (str_starts_with($routeName, $prefix)) {
                return $module;
            }
        }

        return null;
    }
}
