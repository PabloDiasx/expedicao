<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $slug = $this->extractSlugFromHost($request->getHost());

        if (! $slug && config('tenancy.allow_query_parameter', true)) {
            $queryKey = config('tenancy.query_parameter', 'tenant');
            $slug = (string) $request->query($queryKey, '');
        }

        if ($slug !== '') {
            return Tenant::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        }

        $defaultSlug = (string) config('tenancy.default_slug', '');
        if ($defaultSlug !== '') {
            $defaultTenant = Tenant::query()
                ->where('slug', $defaultSlug)
                ->where('is_active', true)
                ->first();

            if ($defaultTenant) {
                return $defaultTenant;
            }
        }

        return null;
    }

    private function extractSlugFromHost(string $host): string
    {
        if ($host === 'localhost' || $host === '127.0.0.1') {
            return '';
        }

        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return (string) $parts[0];
        }

        if (str_ends_with($host, '.localhost') && count($parts) >= 2) {
            return (string) $parts[0];
        }

        $tenant = Tenant::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->first();

        return $tenant?->slug ?? '';
    }
}
