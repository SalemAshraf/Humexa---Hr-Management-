<?php

declare(strict_types=1);

namespace App\Modules\Users\Providers;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

class PlatformUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        /** @var Builder $query */
        $query = parent::newModelQuery($model);

        if (tenancy()->initialized) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('account_type', User::ACCOUNT_TYPE_PLATFORM)
            ->whereNull('tenant_id');
    }
}
