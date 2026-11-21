<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;

test('attendance policy model casts policy flags and limits', function () {
    $policy = new AttendancePolicy([
        'mobile_checkin_enabled' => true,
        'location_verification_enabled' => false,
        'location_accuracy_limit' => 80,
        'allowed_location_radius' => 120,
    ]);

    expect($policy->mobile_checkin_enabled)->toBeTrue()
        ->and($policy->location_verification_enabled)->toBeFalse()
        ->and($policy->location_accuracy_limit)->toBe(80)
        ->and($policy->allowed_location_radius)->toBe(120);
});

test('attendance record casts date and timestamps', function () {
    $record = new AttendanceRecord([
        'attendance_date' => '2026-11-21',
        'check_in_at' => '2026-11-21 08:00:00',
        'check_out_at' => '2026-11-21 17:00:00',
    ]);

    expect($record->attendance_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($record->check_in_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($record->check_out_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
