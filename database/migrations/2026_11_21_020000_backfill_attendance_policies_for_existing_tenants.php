<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    DB::table('attendance_policies')->insertOrIgnore([
                        ['tenant_id' => $tenant->id],
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Intentionally preserve tenant policies: they may have been customized
        // after this backfill and must not be deleted by a rollback.
    }
};
