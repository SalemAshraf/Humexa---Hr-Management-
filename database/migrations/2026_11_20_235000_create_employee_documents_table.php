<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('document_type_id');
            $table->string('title', 190);
            $table->string('original_file_name', 255);
            $table->string('file_path', 500);
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'tenant_id'], 'employee_documents_id_tenant_unique');
            $table->index(['tenant_id', 'employee_id', 'document_type_id'], 'employee_documents_employee_type_index');
            $table->index(['tenant_id', 'expiry_date'], 'employee_documents_expiry_index');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign(['employee_id', 'tenant_id'], 'employee_documents_employee_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employees')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->foreign(['document_type_id', 'tenant_id'], 'employee_documents_type_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('employee_document_types')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->foreign(['uploaded_by_user_id', 'tenant_id'], 'employee_documents_uploader_tenant_foreign')
                ->references(['id', 'tenant_id'])->on('users')
                ->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
