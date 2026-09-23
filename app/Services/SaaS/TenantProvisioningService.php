<?php

namespace App\Services\SaaS;

use App\Models\Property;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;

class TenantProvisioningService
{
    public const PLAN_LIMITS = [
        'starter' => ['max_users' => 3, 'max_properties' => 2],
        'pro' => ['max_users' => 10, 'max_properties' => 10],
        'enterprise' => ['max_users' => 100, 'max_properties' => 1000],
    ];

    public function provisionTenant(string $name, string $slug, string $plan = 'pro'): Tenant
    {
        $limits = self::PLAN_LIMITS[$plan] ?? self::PLAN_LIMITS['pro'];

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'plan' => $plan,
            'max_users' => $limits['max_users'],
            'max_properties' => $limits['max_properties'],
            'feature_flags' => [
                'advanced_analytics' => in_array($plan, ['pro', 'enterprise'], true),
                'custom_webhooks' => in_array($plan, ['pro', 'enterprise'], true),
                'dedicated_api' => $plan === 'enterprise',
            ],
        ]);

        // Default channels & sources
        Source::create([
            'tenant_id' => $tenant->id,
            'channel' => 'direct',
            'label' => 'Site Web Officiel',
        ]);

        return $tenant;
    }

    public function checkLimit(Tenant $tenant, string $resource): bool
    {
        if ($resource === 'properties') {
            $count = Property::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            return $count < ($tenant->max_properties ?? 10);
        }

        if ($resource === 'users') {
            $count = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            return $count < ($tenant->max_users ?? 10);
        }

        return true;
    }
}
