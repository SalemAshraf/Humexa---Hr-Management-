<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Users\Actions\ProvisionTenantRolesAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $provision = app(ProvisionTenantRolesAction::class);

        // Creates roles and permissions only. No account is promoted here:
        // legacy companies stay fail-closed (no company-wide access) until an
        // operator runs `php artisan app:assign-tenant-owner {tenant} {email} --confirm`.
        Tenant::query()->each(fn (Tenant $tenant) => $provision->execute($tenant));
    }

    public function down(): void
    {
        // Roles are kept on rollback.
    }
};
