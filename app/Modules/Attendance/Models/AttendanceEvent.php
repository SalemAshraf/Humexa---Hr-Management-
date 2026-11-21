<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceEvent extends Model
{
    protected $fillable=['tenant_id','attendance_record_id','employee_id','event_type','source','occurred_at','latitude','longitude','location_accuracy','location_id','media_path','device_identifier','metadata'];
    protected function casts():array{return ['occurred_at'=>'datetime','latitude'=>'decimal:7','longitude'=>'decimal:7','metadata'=>'array'];}
}