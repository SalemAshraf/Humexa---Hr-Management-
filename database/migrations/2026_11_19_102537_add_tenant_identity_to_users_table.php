<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');

            $table->string('account_type', 20)
                ->default('tenant')
                ->after('tenant_id');

            $table->index(
                ['tenant_id', 'account_type'],
                'users_tenant_account_type_index'
            );

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);

            $table->dropIndex('users_tenant_account_type_index');

            $table->dropColumn(['tenant_id', 'account_type']);
        });
    }
};
