<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Support\PlatformTeam;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

#[Signature('app:grant-platform-super-admin {email}') ]
#[Description('Grant the Super Admin role to an existing platform account')]
class GrantPlatformSuperAdmin extends Command
{
    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->components->error(
                'This command must run outside tenant context.'
            );

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::query()
            ->where('email', $email)
            ->where('account_type', User::ACCOUNT_TYPE_PLATFORM)
            ->whereNull('tenant_id')
            ->first();

        if (! $user) {
            $this->components->error(
                'No platform account was found for this email.'
            );

            return self::FAILURE;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(PlatformTeam::ID);

        try {
            $role = Role::query()
                ->where('tenant_id', PlatformTeam::ID)
                ->where('name', 'Super Admin')
                ->where('guard_name', 'platform')
                ->first();

            if (! $role) {
                $this->components->error(
                    'Platform authorization is not seeded. Run PlatformAuthorizationSeeder first.'
                );

                return self::FAILURE;
            }

            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }

        $this->components->info(
            "Super Admin role granted to {$user->email}."
        );

        return self::SUCCESS;
    }
}
