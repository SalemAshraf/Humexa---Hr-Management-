<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\Tenant;
use App\Modules\Tenants\Actions\ActivateTenantAction;
use App\Modules\Tenants\Actions\SuspendTenantAction;
use App\Modules\Tenants\Http\Requests\TenantStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Permission;

class PlatformTenantController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorizePlatformPermission($request, 'tenants.view');

        $companies = Tenant::query()
            ->with('domains')
            ->latest('created_at')
            ->paginate(15)
            ->through(fn (Tenant $tenant): array => [
                'id' => $tenant->getKey(),
                'companyName' => $tenant->getAttribute('company_name')
                    ?: 'Unnamed Company',
                'domains' => $tenant->domains->pluck('domain')->values()->all(),
                'status' => $tenant->getAttribute('status'),
                'createdAt' => $tenant->created_at?->toDateTimeString(),
                'suspendedAt' => $tenant->getAttribute('suspended_at'),
            ]);

        $canViewActivityLogs = $this->hasPlatformPermission(
            $request,
            'platform.activity-logs.view'
        );
        $canSuspendCompanies = $this->hasPlatformPermission(
            $request,
            'tenants.suspend'
        );
        $canActivateCompanies = $this->hasPlatformPermission(
            $request,
            'tenants.activate'
        );

        $activity = $canViewActivityLogs
            ? PlatformActivityLog::query()
                ->with('actor:id,name,email')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (PlatformActivityLog $log): array => [
                    'id' => $log->id,
                    'tenantId' => $log->tenant_id,
                    'event' => $log->event,
                    'oldStatus' => $log->old_status,
                    'newStatus' => $log->new_status,
                    'reason' => $log->reason,
                    'actor' => $log->actor?->name ?? 'Deleted platform user',
                    'createdAt' => $log->created_at?->toDateTimeString(),
                ])
            : collect();

        return Inertia::render('Platform/Companies/Index', [
            'companies' => $companies,
            'activity' => $activity,
            'canViewActivityLogs' => $canViewActivityLogs,
            'canSuspendCompanies' => $canSuspendCompanies,
            'canActivateCompanies' => $canActivateCompanies,
        ]);
    }

    public function activityLogs(Request $request): InertiaResponse
    {
        $this->authorizePlatformPermission($request, 'platform.activity-logs.view');

        $activity = PlatformActivityLog::query()
            ->with(['actor:id,name,email', 'tenant'])
            ->latest('created_at')
            ->paginate(25)
            ->through(fn (PlatformActivityLog $log): array => [
                'id' => $log->id,
                'tenantId' => $log->tenant_id,
                'companyName' => $log->tenant?->getAttribute('company_name')
                    ?: 'Deleted or unavailable company',
                'event' => $log->event,
                'oldStatus' => $log->old_status,
                'newStatus' => $log->new_status,
                'reason' => $log->reason,
                'actor' => $log->actor?->name ?? 'Deleted platform user',
                'actorEmail' => $log->actor?->email,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Platform/ActivityLogs/Index', [
            'activity' => $activity,
        ]);
    }

    public function suspend(
        TenantStatusRequest $request,
        Tenant $tenant,
        SuspendTenantAction $action
    ): RedirectResponse {
        return $this->changeStatus(
            $request,
            $tenant,
            'tenants.suspend',
            'company.suspended',
            'suspended',
            fn () => $action->execute((string) $tenant->getKey())
        );
    }

    public function activate(
        TenantStatusRequest $request,
        Tenant $tenant,
        ActivateTenantAction $action
    ): RedirectResponse {
        return $this->changeStatus(
            $request,
            $tenant,
            'tenants.activate',
            'company.activated',
            'active',
            fn () => $action->execute((string) $tenant->getKey())
        );
    }

    private function changeStatus(
        TenantStatusRequest $request,
        Tenant $tenant,
        string $permission,
        string $event,
        string $newStatus,
        callable $operation
    ): RedirectResponse {
        $this->authorizePlatformPermission($request, $permission);

        abort_unless(! tenancy()->initialized, 404);

        DB::transaction(function () use (
            $request,
            $tenant,
            $event,
            $newStatus,
            $operation
        ): void {
            $lockedTenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            $oldStatus = (string) $lockedTenant->getAttribute('status');
            $updatedTenant = $operation();

            // Do not create duplicate audit entries for idempotent requests.
            if ($oldStatus === $newStatus) {
                return;
            }

            PlatformActivityLog::query()->create([
                'actor_id' => $request->user('platform')->getKey(),
                'tenant_id' => (string) $updatedTenant->getKey(),
                'event' => $event,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $request->validated('reason'),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        }, 3);

        return back()->with(
            'status',
            $newStatus === 'suspended'
                ? 'Company suspended successfully. Existing sessions have been revoked.'
                : 'Company activated successfully. All company users must sign in again.'
        );
    }

    private function authorizePlatformPermission(
        Request $request,
        string $permission
    ): void {
        abort_unless($this->hasPlatformPermission($request, $permission), 403);
    }

    private function hasPlatformPermission(
        Request $request,
        string $permission
    ): bool {
        $user = $request->user('platform');

        return ! tenancy()->initialized
            && $user !== null
            && $user->account_type === 'platform'
            && $user->tenant_id === null
            && Permission::query()
                ->where('name', $permission)
                ->where('guard_name', 'platform')
                ->exists()
            && $user->hasPermissionTo($permission, 'platform');
    }
}
