<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAddress extends Model
{
    protected $hidden = ['tenant_id', 'primary_employee_id'];

    protected $fillable = [
        'address_type', 'address_line_1', 'address_line_2', 'city',
        'state', 'postal_code', 'country', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
