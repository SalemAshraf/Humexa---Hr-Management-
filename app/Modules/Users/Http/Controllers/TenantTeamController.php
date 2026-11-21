<?php

declare(strict_types=1);

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Company team accounts and their roles. Owner-only (roles.manage). */
class TenantTeamController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $this->actor($request);

        $linked = \App\Modules\Employees\Models\Employee::query()
            ->where('tenant_id', $tenantId)->whereNotNull('user_id')
            ->get(['id', 'tenant_id', 'user_id', 'first_name', 'middle_name', 'last_name'])->keyBy('user_id');

        $accounts = User::query()
            ->where('tenant_id', $tenantId)
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'employee' => $linked->get($u->id) ? ['id' => $linked->get($u->id)->id, 'name' => $linked->get($u->id)->full_name] : null,
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->getRoleNames()->first(),
                'is_active' => $u->disabled_at === null,
                'is_you' => $u->id === $request->user('tenant')->id,
            ]);

        return Inertia::render('Company/Team/Index', [
            'accounts' => $accounts,
            'roles' => collect(TenantRoleCatalog::roles())->map(fn ($perms, $name) => ['name' => $name, 'permissions' => $perms])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->actor($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:100'],
            'role' => ['required', Rule::in(array_keys(TenantRoleCatalog::roles()))],
        ]);

        $user = new User(['name' => trim($data['name']), 'email' => strtolower(trim($data['email'])), 'password' => $data['password']]);
        $user->forceFill(['tenant_id' => $tenantId, 'account_type' => User::ACCOUNT_TYPE_TENANT, 'must_change_password' => true])->save();
        $user->assignRole($data['role']);

        return back()->with('success', 'Account created. The person must choose their own password at first sign-in. Share the starting password privately.');
    }

    public function updateRole(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $actor = $this->actor($request);

        $target = User::query()
            ->where('tenant_id', $tenantId)
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->findOrFail((int) $request->route('account'));

        $data = $request->validate(['role' => ['required', Rule::in(array_keys(TenantRoleCatalog::roles()))]]);

        if ($target->id === $actor->id) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if ($target->hasRole(TenantRoleCatalog::OWNER) && $data['role'] !== TenantRoleCatalog::OWNER) {
            $owners = User::query()->where('tenant_id', $tenantId)->role(TenantRoleCatalog::OWNER)->count();
            if ($owners <= 1) {
                throw ValidationException::withMessages(['role' => 'A company must keep at least one Owner.']);
            }
        }

        $target->syncRoles([$data['role']]);

        return back()->with('success', 'Role updated.');
    }

    public function setActive(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $target = $this->target($request);
        $active = $request->boolean('active');

        if (! $active) {
            if ($target->id === $actor->id) {
                throw ValidationException::withMessages(['account' => 'You cannot deactivate your own account.']);
            }
            $this->guardLastOwner($target);
        }

        $target->forceFill(['disabled_at' => $active ? null : now()])->save();

        if (! $active) {
            $target->tokens()->delete();
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $target->id)->delete();
        }

        return back()->with('success', $active ? 'Account reactivated.' : 'Account deactivated. Sessions and app tokens were ended.');
    }

    /** Owner picks a new starting password. The person must replace it at next sign-in. */
    public function resetAccess(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $target = $this->target($request);
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:100']]);

        if ($target->id === $actor->id) {
            throw ValidationException::withMessages(['password' => 'Change your own password from your profile.']);
        }

        $target->forceFill(['password' => $data['password'], 'must_change_password' => true])->save();
        $target->tokens()->delete();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $target->id)->delete();

        return back()->with('success', 'Access reset. Share the starting password privately. It must be changed at first sign-in.');
    }

    private function target(Request $request): User
    {
        return User::query()
            ->where('tenant_id', $this->tenantId())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->findOrFail((int) $request->route('account'));
    }

    private function guardLastOwner(User $target): void
    {
        if ($target->hasRole(TenantRoleCatalog::OWNER)) {
            $owners = User::query()->where('tenant_id', $this->tenantId())->whereNull('disabled_at')->role(TenantRoleCatalog::OWNER)->count();
            if ($owners <= 1) {
                throw ValidationException::withMessages(['account' => 'A company must keep at least one active Owner.']);
            }
        }
    }

    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    private function actor(Request $request): User
    {
        $user = $request->user('tenant');
        abort_unless($user instanceof User && hash_equals($this->tenantId(), (string) $user->tenant_id) && $user->can('roles.manage'), 403);

        return $user;
    }
}
