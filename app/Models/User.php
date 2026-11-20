<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Users\Support\PlatformTeam;
use Spatie\Permission\Models\Permission;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use BackedEnum;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    use HasRoles {
        assignRole as protected assignRoleUsingSpatie;
        syncRoles as protected syncRolesUsingSpatie;
        removeRole as protected removeRoleUsingSpatie;

        givePermissionTo as protected givePermissionToUsingSpatie;
        syncPermissions as protected syncPermissionsUsingSpatie;
        revokePermissionTo as protected revokePermissionToUsingSpatie;
    }

    public const ACCOUNT_TYPE_TENANT = 'tenant';

    public const ACCOUNT_TYPE_PLATFORM = 'platform';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getDefaultGuardName(): string
    {
        return $this->account_type === self::ACCOUNT_TYPE_PLATFORM
            ? 'platform'
            : 'tenant';
    }

    public function assignRole(...$roles): static
    {
        $this->assertRolesBelongToCurrentTeam($roles);

        return $this->assignRoleUsingSpatie(...$roles);
    }

    public function syncRoles(...$roles): static
    {
        // Validate all roles before Spatie detaches existing roles.
        $this->assertRolesBelongToCurrentTeam($roles);

        return $this->syncRolesUsingSpatie(...$roles);
    }

    /**
     * Validate role ownership and guard before assigning.
     */
    private function assertRolesBelongToCurrentTeam(array $roles): void
    {
        $expectedTeamId = match ($this->account_type) {
            self::ACCOUNT_TYPE_TENANT => $this->tenant_id,
            self::ACCOUNT_TYPE_PLATFORM => PlatformTeam::ID,
            default => null,
        };

        if ($expectedTeamId === null) {
            throw new AuthorizationException(
                'This account cannot receive roles.'
            );
        }

        if (
            $this->account_type === self::ACCOUNT_TYPE_TENANT
            && (
                ! tenancy()->initialized
                || (string) tenant('id') !== (string) $expectedTeamId
            )
        ) {
            throw new AuthorizationException(
                'The current tenant does not match the user tenant.'
            );
        }

        if (
            $this->account_type === self::ACCOUNT_TYPE_PLATFORM
            && tenancy()->initialized
        ) {
            throw new AuthorizationException(
                'Platform roles cannot be assigned inside tenant context.'
            );
        }

        if (
            (string) getPermissionsTeamId() !== (string) $expectedTeamId
        ) {
            throw new AuthorizationException(
                'The permission team context does not match the user.'
            );
        }

        $guard = $this->getDefaultGuardName();

        $teamKey = config(
            'permission.column_names.team_foreign_key',
            'tenant_id'
        );

        $roleClass = config(
            'permission.models.role',
            Role::class
        );

        foreach (collect($roles)->flatten() as $role) {
            if ($role === null || $role === '') {
                continue;
            }

            if ($role instanceof BackedEnum) {
                $role = $role->value;
            }

            $query = $roleClass::query()
                ->where($teamKey, $expectedTeamId)
                ->where('guard_name', $guard);

            if ($role instanceof Role) {
                $query->whereKey($role->getKey());
                $roleName = $role->name;
            } elseif (
                is_int($role)
                || (
                    is_string($role)
                    && (
                        is_numeric($role)
                        || PermissionRegistrar::isUid($role)
                    )
                )
            ) {
                $query->whereKey($role);
                $roleName = (string) $role;
            } elseif (is_string($role)) {
                $query->where('name', $role);
                $roleName = $role;
            } else {
                throw new \InvalidArgumentException(
                    'Unsupported role value.'
                );
            }

            if (! $query->exists()) {
                throw new RoleDoesNotExist(
                    "There is no role named `{$roleName}` for guard `{$guard}` in the current tenant."
                );
            }
        }
    }

    public function removeRole(...$roles): static
    {
        $this->assertRolesBelongToCurrentTeam($roles);

        return $this->removeRoleUsingSpatie(...$roles);
    }

    public function givePermissionTo(...$permissions): static
    {
        $this->assertPermissionsBelongToCurrentContext($permissions);

        return $this->givePermissionToUsingSpatie(...$permissions);
    }

    public function syncPermissions(...$permissions): static
    {
        // Validate before Spatie detaches existing permissions.
        $this->assertPermissionsBelongToCurrentContext($permissions);

        return $this->syncPermissionsUsingSpatie(...$permissions);
    }

    public function revokePermissionTo($permission): static
    {
        $this->assertPermissionsBelongToCurrentContext([$permission]);

        return $this->revokePermissionToUsingSpatie($permission);
    }

    private function assertPermissionsBelongToCurrentContext(array $permissions): void
    {
        $expectedTeamId = match ($this->account_type) {
            self::ACCOUNT_TYPE_TENANT => $this->tenant_id,
            self::ACCOUNT_TYPE_PLATFORM => PlatformTeam::ID,
            default => null,
        };

        if ($expectedTeamId === null) {
            throw new AuthorizationException(
                'This account cannot receive permissions.'
            );
        }

        if (
            $this->account_type === self::ACCOUNT_TYPE_TENANT
            && (
                ! tenancy()->initialized
                || (string) tenant('id') !== (string) $expectedTeamId
            )
        ) {
            throw new AuthorizationException(
                'The current tenant does not match the user tenant.'
            );
        }

        if (
            $this->account_type === self::ACCOUNT_TYPE_PLATFORM
            && tenancy()->initialized
        ) {
            throw new AuthorizationException(
                'Platform permissions cannot be assigned inside tenant context.'
            );
        }

        if ((string) getPermissionsTeamId() !== (string) $expectedTeamId) {
            throw new AuthorizationException(
                'The permission team context does not match the user.'
            );
        }

        $guard = $this->getDefaultGuardName();

        $permissionClass = config(
            'permission.models.permission',
            Permission::class
        );

        foreach (collect($permissions)->flatten() as $permission) {
            if ($permission === null || $permission === '') {
                continue;
            }

            if ($permission instanceof BackedEnum) {
                $permission = $permission->value;
            }

            $query = $permissionClass::query()
                ->where('guard_name', $guard);

            if ($permission instanceof Permission) {
                $query->whereKey($permission->getKey());
                $permissionName = $permission->name;
            } elseif (
                is_int($permission)
                || (
                    is_string($permission)
                    && (
                        is_numeric($permission)
                        || PermissionRegistrar::isUid($permission)
                    )
                )
            ) {
                $query->whereKey($permission);
                $permissionName = (string) $permission;
            } elseif (is_string($permission)) {
                $query->where('name', $permission);
                $permissionName = $permission;
            } else {
                throw new \InvalidArgumentException(
                    'Unsupported permission value.'
                );
            }

            if (! $query->exists()) {
                throw new \Spatie\Permission\Exceptions\PermissionDoesNotExist(
                    "There is no permission named `{$permissionName}` for guard `{$guard}`."
                );
            }
        }
    }
}
