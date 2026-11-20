<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant
{
    use HasDomains;

    /**
     * Columns stored directly on the central tenants table.
     *
     * Stancl stores attributes not listed here inside the JSON "data" column.
     * These lifecycle fields must remain real columns for indexed queries,
     * database constraints, and reliable session-version updates.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'status',
            'session_version',
            'suspended_at',
        ];
    }
}
