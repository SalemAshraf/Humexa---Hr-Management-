<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLocation extends Model
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'latitude', 'longitude',
        'radius_meters', 'is_active',
    ];

    protected $hidden = ['tenant_id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'tenant_id', 'tenant_id');
    }
}
