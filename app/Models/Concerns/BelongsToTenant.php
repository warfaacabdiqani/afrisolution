<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), app(TenantContext::class)->id());
        });
        static::creating(function ($model) {
            $model->tenant_id = app(TenantContext::class)->id();
        });
        static::updating(function ($model) {
            abort_if($model->isDirty('tenant_id') || (int) $model->tenant_id !== app(TenantContext::class)->id(), 403);
        });
        static::deleting(fn ($model) => abort_unless((int) $model->tenant_id === app(TenantContext::class)->id(), 403));
    }
}
