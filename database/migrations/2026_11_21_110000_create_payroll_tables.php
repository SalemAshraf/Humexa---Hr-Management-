<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name', 150);
            $table->string('country', 2)->default('EG');
            $table->date('effective_from');
            $table->decimal('insurance_employee_rate', 6, 4)->default(0);
            $table->decimal('insurance_employer_rate', 6, 4)->default(0);
            $table->decimal('insurable_min', 12, 2)->default(0);
            $table->decimal('insurable_max', 12, 2)->default(0);
            $table->decimal('personal_exemption_annual', 12, 2)->default(0);
            $table->unsignedSmallInteger('rounding_step')->default(10);
            $table->decimal('private_insurance_cap_pct', 6, 4)->default(0);
            $table->decimal('private_insurance_cap_amount', 12, 2)->default(0);
            $table->decimal('after_tax_levy_rate', 8, 6)->default(0);
            $table->string('after_tax_levy_label', 80)->nullable();
            $table->unsignedSmallInteger('month_days_basis')->default(30);
            $table->json('tax_tables');
            $table->text('sources')->nullable();
            $table->text('open_questions')->nullable();
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id'], 'payroll_rule_sets_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('effective_from');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->boolean('insured')->default(true);
            $table->decimal('insurable_wage', 12, 2)->nullable();
            $table->string('note', 190)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'effective_from']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('rule_set_id');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'year', 'month']);
            $table->unique(['id', 'tenant_id'], 'payroll_runs_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['rule_set_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payroll_rule_sets')->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_deduction', 12, 2)->default(0);
            $table->decimal('adjustments_earnings', 12, 2)->default(0);
            $table->decimal('adjustments_deductions', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('insurable_wage', 12, 2)->default(0);
            $table->decimal('insurance_employee', 12, 2)->default(0);
            $table->decimal('insurance_employer', 12, 2)->default(0);
            $table->decimal('annual_net_income', 14, 2)->default(0);
            $table->decimal('income_tax', 12, 2)->default(0);
            $table->decimal('after_tax_levy', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->unique(['id', 'tenant_id'], 'payslips_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['payroll_run_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payroll_runs')->restrictOnUpdate()->cascadeOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('kind', 12); // earning | deduction
            $table->string('label', 120);
            $table->decimal('amount', 12, 2);
            $table->boolean('taxable')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['payroll_run_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payroll_runs')->restrictOnUpdate()->cascadeOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_compensations');
        Schema::dropIfExists('payroll_rule_sets');
    }
};
