<?php

declare(strict_types=1);

namespace App\Modules\Plans\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'max_employees', 'max_accounts', 'indicative_price_egp', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
