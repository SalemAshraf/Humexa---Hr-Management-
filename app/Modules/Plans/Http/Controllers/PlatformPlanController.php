<?php

declare(strict_types=1);

namespace App\Modules\Plans\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PlatformPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePlans($request);

        $counts = Employee::query()->selectRaw('tenant_id, count(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        return Inertia::render('Platform/Plans/Index', [
            'plans' => Plan::query()->orderBy('max_employees')->get(),
            'companies' => Tenant::query()->latest('created_at')->limit(200)->get()->map(fn (Tenant $t) => [
                'id' => $t->getKey(),
                'name' => $t->getAttribute('company_name') ?: 'Unnamed Company',
                'plan_id' => $t->getAttribute('plan_id'),
                'subscription_ends_at' => $t->getAttribute('subscription_ends_at') ? substr((string) $t->getAttribute('subscription_ends_at'), 0, 10) : null,
                'employees' => (int) ($counts[$t->getKey()] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlans($request);
        Plan::query()->create($this->validated($request));

        return back()->with('success', 'Plan saved.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorizePlans($request);
        $plan->update($this->validated($request, $plan));

        return back()->with('success', 'Plan saved.');
    }

    public function assign(Request $request, string $tenant): RedirectResponse
    {
        $this->authorizePlans($request);
        $data = $request->validate(['plan_id' => ['nullable', 'integer', 'exists:plans,id']]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $t = Tenant::query()->lockForUpdate()->findOrFail($tenant);
            $old = $t->getAttribute('plan_id');
            $t->setAttribute('plan_id', $data['plan_id'] ?? null);
            $t->save();

            PlatformActivityLog::query()->create([
                'actor_id' => $request->user('platform')->getKey(),
                'tenant_id' => (string) $t->getKey(),
                'event' => 'company.plan_changed',
                'old_status' => $old === null ? 'none' : 'plan:' . $old,
                'new_status' => ($data['plan_id'] ?? null) === null ? 'none' : 'plan:' . $data['plan_id'],
                'reason' => null,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        });

        return back()->with('success', 'Company plan updated. Existing employees and accounts are not changed.');
    }

    /** Records "paid until" after an off-platform payment. Nothing is charged, suspended or activated by this. */
    public function subscription(Request $request, string $tenant): RedirectResponse
    {
        $this->authorizePlans($request);
        $data = $request->validate(['subscription_ends_at' => ['nullable', 'date_format:Y-m-d']]);

        DB::transaction(function () use ($request, $tenant, $data): void {
            $t = Tenant::query()->lockForUpdate()->findOrFail($tenant);
            $old = $t->getAttribute('subscription_ends_at');
            $new = $data['subscription_ends_at'] ?? null;
            $t->setAttribute('subscription_ends_at', $new);
            $t->save();

            PlatformActivityLog::query()->create([
                'actor_id' => $request->user('platform')->getKey(),
                'tenant_id' => (string) $t->getKey(),
                'event' => 'company.subscription_changed',
                'old_status' => $old ? 'ends:' . substr((string) $old, 0, 10) : 'none',
                'new_status' => $new ? 'ends:' . $new : 'none',
                'reason' => null,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ]);
        });

        return back()->with('success', 'Subscription end date saved. The company was not suspended or changed.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', \Illuminate\Validation\Rule::unique('plans', 'name')->ignore($plan?->id)],
            'max_employees' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_accounts' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'indicative_price_egp' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function authorizePlans(Request $request): void
    {
        $user = $request->user('platform');

        abort_unless(
            ! tenancy()->initialized
            && $user !== null
            && $user->account_type === 'platform'
            && $user->tenant_id === null
            && Permission::query()->where('name', 'plans.manage')->where('guard_name', 'platform')->exists()
            && $user->hasPermissionTo('plans.manage', 'platform'),
            403
        );
    }
}
