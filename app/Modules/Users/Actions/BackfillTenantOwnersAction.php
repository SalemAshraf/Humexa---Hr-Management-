<?php

declare(strict_types=1);

namespace App\Modules\Users\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Users\Support\TenantRoleCatalog;

/**
 * Gives the Owner role to a company's original registrant only.
 *
 * RegisterCompanyAction creates the company and its first account in one
 * transaction, so the registrant is the lowest-id tenant account created
 * within a few minutes of the company itself. Any other account (created
 * later by other means) is not promoted. Companies where no account fits
 * are returned so a person can decide who the owner is.
 */
class BackfillTenantOwnersAction
{
    public const REGISTRATION_WINDOW_SECONDS = 300;

    public function __construct(private readonly ProvisionTenantRolesAction $provision)
    {
    }

    /**
     * @return list<string> ids of companies where no owner could be identified
     */
    public function execute(Tenant $tenant): array
    {
        $this->provision->execute($tenant);

        $first = User::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->orderBy('id')
            ->first();

        $isRegistrant = $first !== null
            && $tenant->created_at !== null
            && $first->created_at !== null
            && abs($first->created_at->diffInSeconds($tenant->created_at)) <= self::REGISTRATION_WINDOW_SECONDS;

        if (! $isRegistrant) {
            return $first === null ? [] : [(string) $tenant->getTenantKey()];
        }

        $tenant->run(function () use ($first): void {
            if ($first->roles()->doesntExist()) {
                $first->assignRole(TenantRoleCatalog::OWNER);
            }
        });

        return [];
    }
}
