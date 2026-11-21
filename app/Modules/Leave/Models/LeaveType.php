<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'name', 'annual_days', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return ['annual_days' => 'float', 'is_paid' => 'boolean', 'is_active' => 'boolean'];
    }
}
