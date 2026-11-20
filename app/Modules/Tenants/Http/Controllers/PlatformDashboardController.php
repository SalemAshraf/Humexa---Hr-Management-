<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PlatformDashboardController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        // Platform dashboard must only run in the central context.
        abort_unless(! tenancy()->initialized, Response::HTTP_NOT_FOUND);

        $now = now();

        $stats = [
            'totalCompanies' => Tenant::query()->count(),

            'totalTenantUsers' => User::query()
                ->where('account_type', User::ACCOUNT_TYPE_TENANT)
                ->whereNotNull('tenant_id')
                ->count(),

            'newCompaniesThisMonth' => Tenant::query()
                ->where('created_at', '>=', $now->copy()->startOfMonth())
                ->where('created_at', '<', $now->copy()->addMonth()->startOfMonth())
                ->count(),

            'newCompaniesToday' => Tenant::query()
                ->whereDate('created_at', $now->toDateString())
                ->count(),
        ];

        $recentCompanies = Tenant::query()
            ->with('domains')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'data', 'created_at'])
            ->map(fn(Tenant $tenant): array => [
                'id' => $tenant->id,
                'companyName' => $tenant->getAttribute('company_name')
                    ?: 'Unnamed Company',
                'domains' => $tenant->domains
                    ->pluck('domain')
                    ->values()
                    ->all(),
                'createdAt' => $tenant->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Platform/Dashboard', [
            'stats' => $stats,
            'recentCompanies' => $recentCompanies,
        ]);
    }
}
