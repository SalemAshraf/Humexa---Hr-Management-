<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePolicy extends Model
{
    protected $fillable = [
        'tenant_id', 'mobile_checkin_enabled', 'location_verification_enabled',
        'selfie_verification_enabled', 'fingerprint_device_enabled',
        'manual_attendance_enabled', 'attendance_correction_enabled',
        'overtime_requires_approval', 'offline_attendance_enabled',
        'location_accuracy_limit', 'allowed_location_radius',
        'late_grace_minutes', 'minimum_break_minutes',
    ];

    protected function casts(): array
    {
        return [
            'mobile_checkin_enabled' => 'boolean',
            'location_verification_enabled' => 'boolean',
            'selfie_verification_enabled' => 'boolean',
            'fingerprint_device_enabled' => 'boolean',
            'manual_attendance_enabled' => 'boolean',
            'attendance_correction_enabled' => 'boolean',
            'overtime_requires_approval' => 'boolean',
            'offline_attendance_enabled' => 'boolean',
            'location_accuracy_limit' => 'integer',
            'allowed_location_radius' => 'integer',
            'late_grace_minutes' => 'integer',
            'minimum_break_minutes' => 'integer',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(AttendanceLocation::class, 'tenant_id', 'tenant_id');
    }
}
