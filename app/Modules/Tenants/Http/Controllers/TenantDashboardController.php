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
    public function __invoke(Request $request, WorkforceAnalytics $analytics): InertiaResponse|\Illuminate\Http\RedirectResponse
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

        // Self-service accounts hold no company-wide permission, so their home is My space.
        if ($user->getAllPermissions()->isEmpty()) {
            return redirect('/me');
        }

        $teamAccounts = User::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->count();

        $tid = (string) $tenant->getTenantKey();
        // First-run steps are shown only to people who can act on them.
        $setup = $user->can('settings.manage') ? [
            ['key' => 'settings', 'href' => '/settings', 'done' => \App\Modules\Settings\Models\CompanySetting::query()->where('tenant_id', $tid)->exists()],
            ['key' => 'organization', 'href' => '/organization', 'done' => \App\Modules\Organization\Models\Department::query()->where('tenant_id', $tid)->exists()],
            ['key' => 'employees', 'href' => '/employees/import', 'done' => \App\Modules\Employees\Models\Employee::query()->where('tenant_id', $tid)->exists()],
            ['key' => 'team', 'href' => '/team', 'done' => $teamAccounts > 1],
            ['key' => 'attendance', 'href' => '/attendance', 'done' => \App\Modules\Attendance\Models\AttendanceRecord::query()->where('tenant_id', $tid)->exists()],
        ] : [];

        return Inertia::render('Company/Dashboard', [
            'setup' => $setup,
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
                'leave' => $user->can('leave.manage')
                    ? $analytics->leave((string) $tenant->getTenantKey())
                    : null,
                'attendance' => $user->can('attendance.view')
                    ? $analytics->attendanceToday((string) $tenant->getTenantKey())
                    : null,
            ],
        ]);
    }
}
