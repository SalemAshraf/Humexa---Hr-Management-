<?php

declare(strict_types=1);

namespace App\Modules\Users\Providers;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        /** @var Builder $query */
        $query = parent::newModelQuery($model);

        if (! tenancy()->initialized) {
            return $query->whereRaw('1 = 0');
        }

        $tenantId = tenant()->getTenantKey();

        if (! is_string($tenantId) || $tenantId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->where('tenant_id', $tenantId);
    }
}
