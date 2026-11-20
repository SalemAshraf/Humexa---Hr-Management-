<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdentityRecord extends Model
{
    protected $hidden = ['tenant_id', 'identity_number', 'identity_number_hash', 'current_identity_type'];

    protected $fillable = [
        'identity_type', 'identity_number', 'identity_number_hash',
        'issuing_country', 'issue_date', 'expiry_date', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'identity_number' => 'encrypted',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getMaskedNumberAttribute(): ?string
    {
        $number = $this->identity_number;

        if (! is_string($number) || $number === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($number) - 4)).mb_substr($number, -4);
    }
}
