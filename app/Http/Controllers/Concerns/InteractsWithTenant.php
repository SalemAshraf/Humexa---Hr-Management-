<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait InteractsWithTenant
{
    protected function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    protected function tenantUser(Request $request): User
    {
        $user = $request->user('tenant');
        abort_unless($user instanceof User && $user->tenant_id !== null && hash_equals($this->tenantId(), (string) $user->tenant_id), 403);

        return $user;
    }
}
