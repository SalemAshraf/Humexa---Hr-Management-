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

        $accounts = User::query()
            ->where('tenant_id', $tenantId)
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->getRoleNames()->first(),
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
        $user->forceFill(['tenant_id' => $tenantId, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $user->assignRole($data['role']);

        return back()->with('success', 'Account created. Share the password with the person through a private channel.');
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
