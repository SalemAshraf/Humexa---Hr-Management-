
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('id');
            $table->unsignedInteger('session_version')->default(1)->after('status');
            $table->timestamp('suspended_at')->nullable()->after('session_version');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['status']);

            $table->dropColumn([
                'status',
                'session_version',
                'suspended_at',
            ]);
        });
    }
};
