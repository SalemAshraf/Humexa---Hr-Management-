<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('address_type', 30);
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 100);
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_employee_id')
                ->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN employee_id ELSE NULL END');
            $table->timestamps();

            $table->unique(['id', 'tenant_id'], 'employee_addresses_id_tenant_unique');
            $table->unique(['tenant_id', 'primary_employee_id'], 'employee_addresses_primary_unique');
            $table->index(['tenant_id', 'employee_id', 'address_type'], 'employee_addresses_employee_type_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            // MySQL cannot cascade updates to employee_id because primary_employee_id
            // is a stored generated column derived from it. Employee IDs are immutable.
            $table->foreign(['employee_id', 'tenant_id'], 'employee_addresses_employee_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_addresses');
    }
};
