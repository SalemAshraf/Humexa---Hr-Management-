<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Users\Models\Role;
use App\Modules\Users\Support\PlatformTeam;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PlatformAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new \LogicException(
                'Platform permissions must be seeded outside tenant context.'
            );
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(PlatformTeam::ID);

        try {
            $permissions = collect([
                'tenants.view',
                'tenants.suspend',
                'tenants.activate',
                'platform.activity-logs.view',
            ])->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'platform',
            ]));

            $superAdmin = Role::firstOrCreate([
                'tenant_id' => PlatformTeam::ID,
                'name' => 'Super Admin',
                'guard_name' => 'platform',
            ]);

            $superAdmin->syncPermissions($permissions);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }
}
