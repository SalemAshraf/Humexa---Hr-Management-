<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id'], 'branches_id_tenant_unique');
            $table->unique(['tenant_id', 'name'], 'branches_tenant_name_unique');
            $table->unique(['tenant_id', 'code'], 'branches_tenant_code_unique');
            $table->index(['tenant_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id'], 'departments_id_tenant_unique');
            $table->unique(['tenant_id', 'name', 'deleted_at'], 'departments_tenant_name_unique');
            $table->unique(['tenant_id', 'code', 'deleted_at'], 'departments_tenant_code_unique');
            $table->index(['tenant_id', 'branch_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['branch_id', 'tenant_id'], 'departments_branch_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('branches')
                ->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('department_id');
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'department_id', 'name'], 'positions_department_name_unique');
            $table->unique(['tenant_id', 'code', 'deleted_at'], 'positions_tenant_code_unique');
            $table->index(['tenant_id', 'department_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['department_id', 'tenant_id'], 'positions_department_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('departments')
                ->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
    }
};
