<?php

declare(strict_types=1);

namespace App\Modules\Users\Actions;

use App\Models\Tenant;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Support\TenantRoleCatalog;
use Spatie\Permission\Models\Permission;

class ProvisionTenantRolesAction
{
    /**
     * Create or update the default roles for one company. Safe to run again.
     */
    public function execute(Tenant $tenant): void
    {
        $tenant->run(function () use ($tenant): void {
            foreach (TenantRoleCatalog::PERMISSIONS as $name) {
                Permission::findOrCreate($name, 'tenant');
            }

            foreach (TenantRoleCatalog::roles() as $roleName => $permissions) {
                $role = Role::query()
                    ->where('name', $roleName)
                    ->where('guard_name', 'tenant')
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->first()
                    ?? Role::create([
                        'name' => $roleName,
                        'guard_name' => 'tenant',
                        'tenant_id' => $tenant->getTenantKey(),
                    ]);

                $role->syncPermissions($permissions);
            }
        });
    }
}
