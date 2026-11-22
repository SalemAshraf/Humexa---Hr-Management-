<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['id', 'tenant_id'], 'review_cycles_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('review_cycle_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['review_cycle_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['review_cycle_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('review_cycles')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('review_cycles');
    }
};
