<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Users\Actions\ProvisionTenantRolesAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $provision = app(ProvisionTenantRolesAction::class);

        Tenant::query()->each(function (Tenant $tenant) use ($provision): void {
            $provision->execute($tenant);

            // Before roles existed, every tenant account was a full administrator.
            // Keep that access so nobody is locked out by this change.
            User::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->where('account_type', User::ACCOUNT_TYPE_TENANT)
                ->get()
                ->each(function (User $user) use ($tenant): void {
                    $tenant->run(function () use ($user): void {
                        if ($user->roles()->doesntExist()) {
                            $user->assignRole(TenantRoleCatalog::OWNER);
                        }
                    });
                });
        });
    }

    public function down(): void
    {
        // Roles are kept on rollback to avoid removing access.
    }
};
