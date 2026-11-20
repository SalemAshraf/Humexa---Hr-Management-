<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name', 150);
            $table->string('slug', 170);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id'], 'employee_document_types_id_tenant_unique');
            $table->unique(['tenant_id', 'slug'], 'employee_document_types_tenant_slug_unique');
            $table->index(['tenant_id', 'is_active'], 'employee_document_types_active_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_types');
    }
};
