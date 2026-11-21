<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class OvertimeRequest extends Model
{
    protected $fillable=['tenant_id','attendance_record_id','employee_id','requested_minutes','reason','status','reviewed_by_user_id','reviewed_at','review_note'];
    protected function casts():array{return ['requested_minutes'=>'integer','reviewed_at'=>'datetime'];}
}