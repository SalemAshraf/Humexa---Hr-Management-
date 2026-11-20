<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;

class ActivateTenantAction
{
    public function execute(string $tenantId): Tenant
    {
        return DB::transaction(function () use ($tenantId): Tenant {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenantId);

            $status = $tenant->getAttribute('status');

            if ($status === 'active') {
                return $tenant;
            }

            if ($status !== 'suspended') {
                throw new LogicException(
                    "Cannot activate tenant with status: {$status}"
                );
            }

            $tenant->forceFill([
                'status' => 'active',
                'suspended_at' => null,
            ])->save();

            return $tenant->refresh();
        }, 3);
    }
}
