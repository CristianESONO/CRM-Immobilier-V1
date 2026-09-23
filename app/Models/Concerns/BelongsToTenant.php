<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = auth()->user()?->tenant_id ?? session('tenant_id');
            }
        });
    }
}
