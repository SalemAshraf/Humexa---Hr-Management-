<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! tenancy()->initialized) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $tenantId = tenant()->getTenantKey();

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $tenantId
        );
    }
}
