<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('principal', 12, 2);
            $table->decimal('installment', 12, 2);
            $table->unsignedSmallInteger('start_year');
            $table->unsignedTinyInteger('start_month');
            $table->string('reason', 190)->nullable();
            $table->string('status', 20)->default('active'); // active, cancelled
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });

        // One row per approved payroll run that took an installment. The balance is derived from these rows.
        Schema::create('advance_installments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_advance_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['employee_advance_id', 'payroll_run_id']);
            $table->foreign('employee_advance_id')->references('id')->on('employee_advances')->cascadeOnDelete();
            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
        });

        // Company payroll policy. Absent row or dual_control = false keeps the behaviour tenants had before: no extra approval rule.
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->boolean('dual_control')->default(false);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->string('bank_name', 120)->nullable();
            $table->text('bank_account')->nullable(); // stored encrypted by the model
        });
    }

    public function down(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account']);
        });
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('advance_installments');
        Schema::dropIfExists('employee_advances');
    }
};
