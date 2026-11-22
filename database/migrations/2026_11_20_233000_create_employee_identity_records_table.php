<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_identity_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('identity_type', 30);
            $table->text('identity_number');
            $table->char('identity_number_hash', 64);
            $table->string('issuing_country', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('current_identity_type', 30)
                ->nullable()
                ->storedAs('CASE WHEN is_current = 1 THEN identity_type ELSE NULL END');
            $table->timestamps();

            $table->unique(['id', 'tenant_id'], 'employee_identity_records_id_tenant_unique');
            $table->unique(['tenant_id', 'employee_id', 'current_identity_type'], 'employee_identity_current_unique');
            $table->unique(['tenant_id', 'identity_type', 'identity_number_hash'], 'employee_identity_hash_unique');
            $table->index(['tenant_id', 'employee_id', 'identity_type'], 'employee_identity_employee_type_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['employee_id', 'tenant_id'], 'employee_identity_employee_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_identity_records');
    }
};
