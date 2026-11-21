<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Users\Actions\BackfillTenantOwnersAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void
    {
        $backfill = app(BackfillTenantOwnersAction::class);

        Tenant::query()->each(function (Tenant $tenant) use ($backfill): void {
            $ambiguous = $backfill->execute($tenant);

            foreach ($ambiguous as $tenantId) {
                Log::warning('No registrant account identified; company has no Owner until one is assigned.', [
                    'tenant_id' => $tenantId,
                ]);
            }
        });

        // Only the original registrant of each company is promoted. All other
        // existing accounts keep no company-wide permission until assigned a role.
    }

    public function down(): void
    {
        // Roles are kept on rollback.
    }
};
