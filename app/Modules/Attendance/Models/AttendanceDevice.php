<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceDevice extends Model
{
    protected $fillable=['tenant_id','branch_id','name','vendor','device_identifier','integration_type','status','last_synced_at','configuration'];
    protected function casts():array{return ['last_synced_at'=>'datetime','configuration'=>'array'];}
}