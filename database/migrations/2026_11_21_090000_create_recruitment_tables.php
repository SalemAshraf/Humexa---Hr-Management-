<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Users\Actions\ProvisionTenantRolesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title', 150);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('employment_type', 30)->default('full_time');
            $table->unsignedSmallInteger('positions')->default(1);
            $table->string('status', 20)->default('open');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->unique(['id', 'tenant_id'], 'job_openings_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['department_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('departments')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('job_opening_id');
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('source', 60)->nullable();
            $table->string('stage', 20)->default('applied');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'job_opening_id', 'stage']);
            $table->unique(['id', 'tenant_id'], 'candidates_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['job_opening_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('job_openings')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('candidate_stage_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('candidate_id');
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign(['candidate_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('candidates')->restrictOnUpdate()->cascadeOnDelete();
        });

        // New permissions for existing companies. Creates roles and permissions only; promotes nobody.
        $provision = app(ProvisionTenantRolesAction::class);
        Tenant::query()->each(fn (Tenant $tenant) => $provision->execute($tenant));
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_stage_events');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
    }
};
