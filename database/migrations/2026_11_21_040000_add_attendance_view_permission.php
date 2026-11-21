<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'attendance.view',
            'guard_name' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'attendance.view')
            ->where('guard_name', 'tenant')
            ->delete();
    }
};
