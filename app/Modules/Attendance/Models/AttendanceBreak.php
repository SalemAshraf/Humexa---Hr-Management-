<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceBreak extends Model
{
    protected $hidden = ['tenant_id'];
    protected $fillable=['tenant_id','attendance_record_id','started_at','ended_at'];
    protected function casts():array{return ['started_at'=>'datetime','ended_at'=>'datetime'];}
}