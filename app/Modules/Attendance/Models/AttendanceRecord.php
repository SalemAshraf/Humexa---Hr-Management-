<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AttendanceRecord extends Model
{
    protected $fillable=['tenant_id','employee_id','attendance_date','check_in_at','check_out_at','check_in_source','check_out_source','status','worked_minutes','late_minutes','early_leave_minutes','overtime_minutes','notes'];
    protected function casts(): array{return ['attendance_date'=>'date','check_in_at'=>'datetime','check_out_at'=>'datetime'];}
    public function events():HasMany{return $this->hasMany(AttendanceEvent::class);}
    public function breaks():HasMany{return $this->hasMany(AttendanceBreak::class);}
}