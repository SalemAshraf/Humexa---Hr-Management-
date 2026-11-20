<?php

declare(strict_types=1);

namespace App\Modules\Users\Models;

use App\Models\User;
use App\Modules\Users\Support\PlatformTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    private function assertCurrentTeamOwnsRole(): void
    {
        $expectedTeamId = $this->tenant_id;

        if (
            $expectedTeamId === null
            || (string) getPermissionsTeamId() !== (string) $expectedTeamId
        ) {
            throw new AuthorizationException(
                'The current permission team does not own this role.'
            );
        }

        if ($expectedTeamId === PlatformTeam::ID) {
            if (tenancy()->initialized) {
                throw new AuthorizationException(
                    'Platform roles cannot be modified inside tenant context.'
                );
            }

            return;
        }

        if (
            ! tenancy()->initialized
            || (string) tenant('id') !== (string) $expectedTeamId
        ) {
            throw new AuthorizationException(
                'The current tenant does not own this role.'
            );
        }
    }

    public function givePermissionTo(...$permissions): static
    {
        $this->assertCurrentTeamOwnsRole();

        return parent::givePermissionTo(...$permissions);
    }

    public function syncPermissions(...$permissions): static
    {
        $this->assertCurrentTeamOwnsRole();

        return parent::syncPermissions(...$permissions);
    }

    public function revokePermissionTo($permission): static
    {
        $this->assertCurrentTeamOwnsRole();

        return parent::revokePermissionTo($permission);
    }
}
