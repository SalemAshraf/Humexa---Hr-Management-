<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveSetting extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'weekend_days'];

    protected function casts(): array
    {
        return ['weekend_days' => 'array'];
    }
}
