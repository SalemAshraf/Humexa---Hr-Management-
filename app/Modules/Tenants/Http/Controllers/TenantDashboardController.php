<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenants\Services\WorkforceAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, WorkforceAnalytics $analytics): InertiaResponse
    {
        abort_unless(tenancy()->initialized, 404);

        $tenant = tenant();
        $user = $request->user('tenant');

        abort_unless(
            $user instanceof User
            && $user->account_type === User::ACCOUNT_TYPE_TENANT
            && $user->tenant_id !== null
            && hash_equals((string) $tenant->getTenantKey(), (string) $user->tenant_id),
            403
        );

        $teamAccounts = User::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->count();

        return Inertia::render('Company/Dashboard', [
            'company' => [
                'name' => $tenant->getAttribute('company_name') ?: 'Your Company',
                'domain' => $request->getHost(),
                'status' => (string) ($tenant->getAttribute('status') ?: 'active'),
                'createdAt' => $tenant->created_at?->toDateString(),
            ],
            'stats' => [
                'teamAccounts' => $teamAccounts,
            ],
            // Each block is sent only to users who hold the matching permission.
            'analytics' => [
                'headcount' => $user->can('employees.view')
                    ? $analytics->headcount((string) $tenant->getTenantKey())
                    : null,
                'attendance' => $user->can('attendance.view')
                    ? $analytics->attendanceToday((string) $tenant->getTenantKey())
                    : null,
            ],
        ]);
    }
}
