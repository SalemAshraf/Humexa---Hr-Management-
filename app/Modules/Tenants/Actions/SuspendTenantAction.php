<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;

class SuspendTenantAction
{
    public function execute(string $tenantId): Tenant
    {
        return DB::transaction(function () use ($tenantId): Tenant {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenantId);

            $status = $tenant->getAttribute('status');

            if ($status === 'suspended') {
                return $tenant;
            }

            if ($status !== 'active') {
                throw new LogicException(
                    "Cannot suspend tenant with status: {$status}"
                );
            }

            $tenant->forceFill([
                'status' => 'suspended',
                'session_version' => (int) $tenant->getAttribute('session_version') + 1,
                'suspended_at' => now(),
            ])->save();

            return $tenant->refresh();
        }, 3);
    }
}
