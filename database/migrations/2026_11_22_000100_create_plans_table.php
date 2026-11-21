<?php

use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedInteger('max_employees')->nullable(); // null = no limit
            $table->unsignedInteger('max_accounts')->nullable();  // sign-in accounts; null = no limit
            $table->unsignedInteger('indicative_price_egp')->nullable(); // shown only, nothing is charged
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        \Illuminate\Support\Facades\DB::table('plans')->insert([
            ['name' => 'Starter', 'max_employees' => 25, 'max_accounts' => 5, 'indicative_price_egp' => 750, 'notes' => 'Starting point only. Edit freely.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Growth', 'max_employees' => 100, 'max_accounts' => 15, 'indicative_price_egp' => 2500, 'notes' => 'Starting point only. Edit freely.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Business', 'max_employees' => 500, 'max_accounts' => 50, 'indicative_price_egp' => null, 'notes' => 'Price on request. Starting point only.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('status');
            $table->index('plan_id');
        });

        // Adds the new platform permission to existing Super Admin roles (idempotent).
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            (new PlatformAuthorizationSeeder())->run();
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
