<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = auth()->user()?->tenant_id ?? session('tenant_id');

        // Apply scope unless user is cross-tenant super_admin
        if ($tenantId && auth()->user()?->role !== 'super_admin') {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
