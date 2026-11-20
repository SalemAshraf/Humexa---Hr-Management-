<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEmergencyContact extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = [
        'full_name', 'relationship', 'phone', 'alternate_phone',
        'email', 'notes', 'priority',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
