<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('full_name', 190);
            $table->string('relationship', 100);
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('priority')->default(1);
            $table->timestamps();

            $table->unique(['id', 'tenant_id'], 'employee_emergency_contacts_id_tenant_unique');
            $table->unique(['tenant_id', 'employee_id', 'priority'], 'employee_emergency_contacts_priority_unique');
            $table->index(['tenant_id', 'employee_id'], 'employee_emergency_contacts_employee_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['employee_id', 'tenant_id'], 'employee_emergency_employee_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_emergency_contacts');
    }
};
