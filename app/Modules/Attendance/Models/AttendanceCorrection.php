<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = [
        'tenant_id', 'attendance_session_id', 'employee_id', 'requested_by_user_id',
        'requested_check_in', 'requested_check_out', 'reason', 'status',
        'reviewed_by_user_id', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_check_in' => 'datetime',
            'requested_check_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }
}
