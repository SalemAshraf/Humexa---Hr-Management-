<?php

declare(strict_types=1);

namespace App\Modules\Users\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Users\Support\TenantRoleCatalog;
use InvalidArgumentException;

/**
 * Explicit, operator-confirmed Owner assignment for one company.
 *
 * Legacy companies have no durable record of who created them, so nobody is
 * promoted automatically. An operator names the company and the account.
 */
class AssignTenantOwnerAction
{
    public function __construct(private readonly ProvisionTenantRolesAction $provision)
    {
    }

    public function execute(Tenant $tenant, string $email): User
    {
        $user = User::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->where('email', strtolower(trim($email)))
            ->first();

        if ($user === null) {
            throw new InvalidArgumentException('No account with this email belongs to this company.');
        }

        $this->provision->execute($tenant);

        $tenant->run(fn () => $user->assignRole(TenantRoleCatalog::OWNER));

        return $user;
    }
}
