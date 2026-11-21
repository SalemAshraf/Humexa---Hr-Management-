<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->boolean('mobile_checkin_enabled')->default(true);
            $table->boolean('location_verification_enabled')->default(false);
            $table->boolean('selfie_verification_enabled')->default(false);
            $table->boolean('fingerprint_device_enabled')->default(false);
            $table->boolean('manual_attendance_enabled')->default(false);
            $table->boolean('attendance_correction_enabled')->default(true);
            $table->boolean('overtime_requires_approval')->default(true);
            $table->boolean('offline_attendance_enabled')->default(false);
            $table->unsignedInteger('location_accuracy_limit')->default(100);
            $table->unsignedInteger('allowed_location_radius')->default(150);
            $table->unsignedInteger('late_grace_minutes')->default(0);
            $table->unsignedInteger('minimum_break_minutes')->default(0);
            $table->timestamps();
            $table->unique('tenant_id');
            $table->unique(['id', 'tenant_id'], 'attendance_policies_id_tenant_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['id', 'tenant_id'], 'attendance_locations_id_tenant_unique');
            $table->index(['tenant_id', 'is_active']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('check_in_source', 30)->nullable();
            $table->string('check_out_source', 30)->nullable();
            $table->string('status', 30)->default('recorded');
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id'], 'attendance_records_id_tenant_unique');
            $table->unique(['tenant_id', 'employee_id', 'attendance_date'], 'attendance_daily_unique');
            $table->index(['tenant_id', 'attendance_date', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('attendance_record_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('event_type', 30);
            $table->string('source', 30);
            $table->timestamp('occurred_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('media_path')->nullable();
            $table->string('device_identifier')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'occurred_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['attendance_record_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_records')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['location_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_locations')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('attendance_record_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'attendance_record_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['attendance_record_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_records')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('attendance_record_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->timestamp('requested_check_in')->nullable();
            $table->timestamp('requested_check_out')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['attendance_record_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_records')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['requested_by_user_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['reviewed_by_user_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('attendance_record_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('requested_minutes');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['attendance_record_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_records')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['reviewed_by_user_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $unsigned = $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('vendor', 100)->nullable();
            $table->string('device_identifier', 190);
            $table->string('integration_type', 50);
            $table->string('status', 30)->default('inactive');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id'], 'attendance_devices_id_tenant_unique');
            $table->unique(['tenant_id', 'device_identifier']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['branch_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('branches')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('attendance_device_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('external_event_id', 190);
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->string('processing_status', 30)->default('received');
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'device_id', 'external_event_id'], 'device_event_dedupe');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['device_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_events');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_locations');
        Schema::dropIfExists('attendance_policies');
    }
};