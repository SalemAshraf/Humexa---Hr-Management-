<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Performance\Models\PerformanceReview;
use App\Modules\Performance\Models\ReviewCycle;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);

    $this->reg = app(RegisterCompanyAction::class)->execute(
        companyName: 'Team Co', adminName: 'Team Owner', email: 'team-owner@tenant-test.example', password: 'StrongPassword123!',
    );
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
    $this->roleOf = fn (User $u) => $this->reg['tenant']->run(fn () => $u->fresh()->getRoleNames()->all());
});


function emp($t, string $first, ?int $managerId = null, ?int $userId = null): Employee {
    return Employee::query()->forceCreate([
        'tenant_id' => $t->tid, 'employee_number' => 'P-'.$first, 'first_name' => $first, 'last_name' => 'Test',
        'employment_status' => 'active', 'hire_date' => '2024-01-01', 'manager_id' => $managerId, 'user_id' => $userId,
    ]);
}

test('hr runs a cycle, a manager reviews only direct reports, hr finalizes', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-perf@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-perf@tenant-test.example');
    $mgrEmp = emp($this, 'Boss', null, $mgr->id);
    $report = emp($this, 'Report', $mgrEmp->id);
    $stranger = emp($this, 'Stranger');

    ($this->as)($hr)->post("{$this->base}/performance/cycles", ['name' => '2026 H2', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'status' => 'open'])->assertRedirect();
    $cycle = ReviewCycle::where('tenant_id', $this->tid)->firstOrFail();

    ($this->as)($mgr)->post("{$this->base}/performance/cycles", ['name' => 'Nope', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01', 'status' => 'open'])->assertForbidden();
    ($this->as)($mgr)->post("{$this->base}/performance/reviews", ['review_cycle_id' => $cycle->id, 'employee_id' => $stranger->id])->assertForbidden();
    ($this->as)($mgr)->post("{$this->base}/performance/reviews", ['review_cycle_id' => $cycle->id, 'employee_id' => $mgrEmp->id])->assertForbidden();
    ($this->as)($mgr)->post("{$this->base}/performance/reviews", ['review_cycle_id' => $cycle->id, 'employee_id' => $report->id])->assertRedirect();

    $review = PerformanceReview::where('tenant_id', $this->tid)->firstOrFail();
    ($this->as)($mgr)->put("{$this->base}/performance/reviews/{$review->id}", ['status' => 'submitted'])->assertSessionHasErrors('rating');
    ($this->as)($mgr)->put("{$this->base}/performance/reviews/{$review->id}", ['rating' => 4, 'strengths' => 'Reliable', 'status' => 'submitted'])->assertRedirect();
    ($this->as)($mgr)->post("{$this->base}/performance/reviews/{$review->id}/finalize")->assertForbidden();

    ($this->as)($hr)->post("{$this->base}/performance/reviews/{$review->id}/finalize")->assertRedirect();
    expect($review->fresh()->status)->toBe('finalized');
    ($this->as)($mgr)->put("{$this->base}/performance/reviews/{$review->id}", ['rating' => 1, 'status' => 'draft'])->assertStatus(422);
    ($this->as)($hr)->delete("{$this->base}/performance/reviews/{$review->id}")->assertSessionHasErrors('status');
    ($this->as)($hr)->delete("{$this->base}/performance/cycles/{$cycle->id}")->assertSessionHasErrors('cycle');

    ($this->as)($hr)->put("{$this->base}/performance/cycles/{$cycle->id}", ['name' => '2026 H2', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'status' => 'closed'])->assertRedirect();
    ($this->as)($hr)->post("{$this->base}/performance/reviews", ['review_cycle_id' => $cycle->id, 'employee_id' => $stranger->id])->assertStatus(422);

    ($this->as)($mgr)->get("{$this->base}/performance")->assertOk()->assertInertia(fn ($p) => $p->where('abilities.manage', false)->where('scope', 'direct_reports'));
});

test('employees cannot open performance and other companies stay hidden', function () {
    $employee = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-perf@tenant-test.example');
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-perf2@tenant-test.example');
    ($this->as)($employee)->get("{$this->base}/performance")->assertForbidden();

    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Perf Other', adminName: 'O', email: 'o@perf-other.example', password: 'StrongPassword123!');
    $foreign = ReviewCycle::create(['tenant_id' => (string) $other['tenant']->id, 'name' => 'Theirs', 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'status' => 'open']);
    ($this->as)($hr)->put("{$this->base}/performance/cycles/{$foreign->id}", ['name' => 'x', 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'status' => 'open'])->assertNotFound();
    ($this->as)($hr)->post("{$this->base}/performance/reviews", ['review_cycle_id' => $foreign->id, 'employee_id' => 1])->assertNotFound();
});
