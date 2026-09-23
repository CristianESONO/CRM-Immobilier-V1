<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class TenantApiMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenantSlug = $request->header('X-Tenant-Key') ?? $request->header('X-Tenant-Slug');

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            if ($tenant) {
                app()->instance(Tenant::class, $tenant);
                $request->attributes->set('tenant', $tenant);

                return $next($request);
            }
        }

        $firstTenant = Tenant::first();
        if ($firstTenant) {
            app()->instance(Tenant::class, $firstTenant);
            $request->attributes->set('tenant', $firstTenant);

            return $next($request);
        }

        return response()->json(['message' => 'En-tête X-Tenant-Key manquant ou tenant invalide'], 401);
    }
}
