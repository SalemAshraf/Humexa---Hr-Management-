<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // "Paid until", typed by a platform admin after an off-platform payment. Informational only.
            $table->date('subscription_ends_at')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('subscription_ends_at'));
    }
};
