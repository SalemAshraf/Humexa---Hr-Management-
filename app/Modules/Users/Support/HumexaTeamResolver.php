<?php

declare(strict_types=1);

namespace App\Modules\Users\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\DefaultTeamResolver;

final class HumexaTeamResolver extends DefaultTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        if (tenancy()->initialized) {
            return tenant('id');
        }

        $platformUser = Auth::guard('platform')->user();

        if (
            $platformUser instanceof User
            && $platformUser->account_type === User::ACCOUNT_TYPE_PLATFORM
            && $platformUser->tenant_id === null
        ) {
            return PlatformTeam::ID;
        }

        if (app()->runningInConsole()) {
            return parent::getPermissionsTeamId();
        }

        return null;
    }
}