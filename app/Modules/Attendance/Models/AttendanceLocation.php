<?php

declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceLocation extends Model
{
    protected $fillable=['tenant_id','branch_id','name','latitude','longitude','radius_meters','is_active'];
    protected function casts(): array{return ['latitude'=>'decimal:7','longitude'=>'decimal:7','radius_meters'=>'integer','is_active'=>'boolean'];}
}