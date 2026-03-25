<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $response;
        }

        $user = $request->user();

        Log::channel('audit')->info('action', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'tenant_id' => $user?->tenant_id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
