<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only platform pages: who has platform access, what needs attention, and how the platform is set up. */
class PlatformInfoController extends Controller
{
    private function authorizeView(Request $request): void
    {
        $user = $request->user('platform');
        abort_unless(
            $user instanceof User
            && $user->account_type === User::ACCOUNT_TYPE_PLATFORM
            && $user->tenant_id === null
            && $user->hasPermissionTo('tenants.view', 'platform'),
            403
        );
    }

    public function users(Request $request): Response
    {
        $this->authorizeView($request);

        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.guard_name', 'platform')
            ->where('model_has_roles.model_type', User::class)
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $users = User::query()->where('account_type', User::ACCOUNT_TYPE_PLATFORM)->whereNull('tenant_id')
            ->orderBy('name')->get(['id', 'name', 'email', 'disabled_at', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $roles[$u->id] ?? [],
                'active' => $u->disabled_at === null,
                'created_at' => $u->created_at?->toDateString(),
                'is_you' => $u->id === $request->user('platform')->id,
            ]);

        return Inertia::render('Platform/Users/Index', ['users' => $users]);
    }

    public function notifications(Request $request, PlanLimits $limits): Response
    {
        $this->authorizeView($request);

        $tenants = Tenant::query()->latest('created_at')->limit(300)->get();
        $name = fn (Tenant $t) => (string) ($t->getAttribute('company_name') ?: $t->getKey());
        $items = [];

        foreach ($tenants as $t) {
            if ($t->created_at && $t->created_at->gte(now()->subDays(14))) {
                $items[] = ['kind' => 'new', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => $t->created_at->toDateString()];
            }
            if ($t->status === 'suspended') {
                $items[] = ['kind' => 'suspended', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => $t->suspended_at?->toDateString()];
            }
            // Same calendar day the company's own access check uses (the company timezone setting).
            $today = \Illuminate\Support\Carbon::parse(\App\Modules\Settings\Support\CompanyClock::today((string) $t->getKey()));
            $ends = $t->getAttribute('subscription_ends_at');
            if ($ends) {
                $days = (int) $today->diffInDays(\Illuminate\Support\Carbon::parse((string) $ends)->startOfDay(), false);
                if ($days < 0) {
                    $items[] = ['kind' => 'sub_expired', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => substr((string) $ends, 0, 10)];
                } elseif ($days <= 14) {
                    $items[] = ['kind' => 'sub_expiring', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => substr((string) $ends, 0, 10)];
                }
            }
            $trial = $t->getAttribute('trial_ends_at');
            if ($trial && ! $ends) {
                $tdays = (int) $today->diffInDays(\Illuminate\Support\Carbon::parse((string) $trial)->startOfDay(), false);
                if ($tdays < 0) {
                    $items[] = ['kind' => 'trial_ended', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => substr((string) $trial, 0, 10)];
                } elseif ($tdays <= 3) {
                    $items[] = ['kind' => 'trial_ending', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => substr((string) $trial, 0, 10)];
                }
            }
            if ($t->plan_id === null) {
                $items[] = ['kind' => 'no_plan', 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => null];

                continue;
            }
            $u = $limits->usage((string) $t->getKey());
            foreach (['employees' => 'max_employees', 'accounts' => 'max_accounts'] as $used => $maxKey) {
                $max = $u['plan'][$maxKey] ?? null;
                if ($max && $u[$used] >= $max * 0.8) {
                    $items[] = ['kind' => $u[$used] >= $max ? 'at_limit' : 'near_limit', 'what' => $used, 'used' => $u[$used], 'max' => $max, 'company_id' => $t->getKey(), 'company' => $name($t), 'date' => null];
                }
            }
        }

        return Inertia::render('Platform/Notifications/Index', ['items' => $items]);
    }

    public function settings(Request $request): Response
    {
        $this->authorizeView($request);

        $mailer = (string) config('mail.default');

        return Inertia::render('Platform/Settings/Index', [
            'info' => [
                'tenant_domain' => (string) config('humexa.tenant_domain'),
                'environment' => (string) config('app.env'),
                'default_locale' => (string) config('app.locale'),
                'email_sending' => ! in_array($mailer, ['log', 'array'], true),
                'companies' => Tenant::query()->count(),
                'plans' => Plan::query()->count(),
                'platform_users' => User::query()->where('account_type', User::ACCOUNT_TYPE_PLATFORM)->whereNull('tenant_id')->count(),
            ],
        ]);
    }
}
