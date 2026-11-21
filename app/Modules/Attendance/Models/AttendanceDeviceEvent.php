<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceDeviceEvent extends Model
{
    protected $fillable=['tenant_id','device_id','employee_id','external_event_id','occurred_at','payload','processing_status','processing_error'];
    protected function casts():array{return ['occurred_at'=>'datetime','payload'=>'array'];}
}