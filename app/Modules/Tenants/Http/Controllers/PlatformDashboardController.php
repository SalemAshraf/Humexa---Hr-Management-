<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Permission;

class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): InertiaResponse
    {
        abort_unless(! tenancy()->initialized, Response::HTTP_NOT_FOUND);

        $user = $request->user('platform');
        $canViewCompanies = $user !== null
            && $user->account_type === User::ACCOUNT_TYPE_PLATFORM
            && $user->tenant_id === null
            && Permission::query()->where('name', 'tenants.view')->where('guard_name', 'platform')->exists()
            && $user->hasPermissionTo('tenants.view', 'platform');

        abort_unless($canViewCompanies, Response::HTTP_FORBIDDEN);

        $canViewActivityLogs = Permission::query()
            ->where('name', 'platform.activity-logs.view')
            ->where('guard_name', 'platform')
            ->exists()
            && $user->hasPermissionTo('platform.activity-logs.view', 'platform');

        $now = now();
        $total = Tenant::query()->count();
        $statusCounts = Tenant::query()->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status')->all();
        $active = (int) ($statusCounts['active'] ?? 0);
        $suspended = (int) ($statusCounts['suspended'] ?? 0);
        $settingUp = max(0, $total - $active - $suspended);

        $growth = collect(range(5, 0))->map(function (int $offset) use ($now): array {
            $month = $now->copy()->startOfMonth()->subMonths($offset);
            return [
                'label' => $month->format('M'),
                'count' => Tenant::query()->where('created_at', '>=', $month)
                    ->where('created_at', '<', $month->copy()->addMonth())->count(),
            ];
        })->values()->all();

        $registrationActivity = collect(range(6, 0))->map(function (int $offset) use ($now): array {
            $day = $now->copy()->startOfDay()->subDays($offset);
            return [
                'label' => $day->format('d'),
                'count' => Tenant::query()->where('created_at', '>=', $day)
                    ->where('created_at', '<', $day->copy()->addDay())->count(),
            ];
        })->values()->all();

        $recentCompanies = Tenant::query()->with('domains')->latest('created_at')->limit(5)
            ->get(['id', 'data', 'status', 'created_at'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'companyName' => $tenant->getAttribute('company_name') ?: 'Unnamed Company',
                'domains' => $tenant->domains->pluck('domain')->values()->all(),
                'status' => $tenant->getAttribute('status') ?: 'setting_up',
                'createdAt' => $tenant->created_at?->format('M d, Y'),
            ])->values()->all();

        $recentActivity = $canViewActivityLogs
            ? PlatformActivityLog::query()->with(['actor:id,name', 'tenant'])->latest()->limit(5)
                ->get()->map(fn (PlatformActivityLog $log): array => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'companyName' => $log->tenant?->getAttribute('company_name') ?: 'Unavailable company',
                    'actor' => $log->actor?->name ?: 'Platform administrator',
                    'createdAt' => $log->created_at?->diffForHumans(),
                    'newStatus' => $log->new_status,
                ])->values()->all()
            : [];

        return Inertia::render('Platform/Dashboard', [
            'stats' => [
                'totalCompanies' => $total,
                'totalTenantUsers' => User::query()->where('account_type', User::ACCOUNT_TYPE_TENANT)
                    ->whereNotNull('tenant_id')->count(),
                'newCompaniesThisMonth' => Tenant::query()->where('created_at', '>=', $now->copy()->startOfMonth())
                    ->where('created_at', '<', $now->copy()->addMonth()->startOfMonth())->count(),
                'newCompaniesToday' => Tenant::query()->whereDate('created_at', $now->toDateString())->count(),
                'activeCompanies' => $active,
                'suspendedCompanies' => $suspended,
                'settingUpCompanies' => $settingUp,
                'activeRate' => $total > 0 ? round(($active / $total) * 100) : 0,
                'suspendedRate' => $total > 0 ? round(($suspended / $total) * 100, 1) : 0,
            ],
            'growth' => $growth,
            'registrationActivity' => $registrationActivity,
            'recentCompanies' => $recentCompanies,
            'recentActivity' => $recentActivity,
            'canViewActivityLogs' => $canViewActivityLogs,
        ]);
    }
}
