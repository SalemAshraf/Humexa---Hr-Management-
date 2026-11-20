<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite references enforce tenant ownership at the database level.
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id'], 'users_id_tenant_unique');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id'], 'positions_id_tenant_unique');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('employee_number', 50);
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('work_email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();

            $table->date('hire_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->string('employment_status', 30)->default('draft');
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();

            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id'], 'employees_id_tenant_unique');
            $table->unique(['tenant_id', 'employee_number'], 'employees_tenant_number_unique');
            $table->unique('user_id', 'employees_user_unique');
            $table->unique(['tenant_id', 'work_email'], 'employees_tenant_email_unique');

            $table->index(['tenant_id', 'employment_status'], 'employees_tenant_status_index');
            $table->index(['tenant_id', 'branch_id'], 'employees_tenant_branch_index');
            $table->index(['tenant_id', 'department_id'], 'employees_tenant_department_index');
            $table->index(['tenant_id', 'position_id'], 'employees_tenant_position_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['user_id', 'tenant_id'], 'employees_user_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['branch_id', 'tenant_id'], 'employees_branch_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('branches')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['department_id', 'tenant_id'], 'employees_department_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('departments')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['position_id', 'tenant_id'], 'employees_position_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('positions')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['manager_id', 'tenant_id'], 'employees_manager_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('employee_employment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'created_at'], 'employee_history_timeline_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['employee_id', 'tenant_id'], 'employee_history_employee_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['changed_by_user_id', 'tenant_id'], 'employee_history_user_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_employment_histories');
        Schema::dropIfExists('employees');

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique('positions_id_tenant_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_tenant_unique');
        });
    }
};
