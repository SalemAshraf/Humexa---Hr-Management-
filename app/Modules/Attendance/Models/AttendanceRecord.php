<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'tenant_id', 'employee_id', 'attendance_date', 'status',
        'total_worked_minutes', 'total_break_minutes', 'late_minutes',
        'early_leave_minutes', 'overtime_minutes', 'notes',
    ];

    protected $hidden = ['tenant_id'];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'total_worked_minutes' => 'integer',
            'total_break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderBy('session_number');
    }
}
