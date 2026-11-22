<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceShift extends Model
{
    protected $fillable = ['tenant_id', 'name', 'start_time', 'end_time', 'grace_minutes'];

    protected $hidden = ['tenant_id'];
}
