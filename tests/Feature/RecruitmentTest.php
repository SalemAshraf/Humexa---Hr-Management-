<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\JobOpening;
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


function recruitJob($t, array $o = []) {
    return JobOpening::create($o + ['tenant_id' => $t->tid, 'title' => 'Accountant', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
}

test('hr can run a job opening and move a candidate through to a draft employee', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-rec@tenant-test.example');

    ($this->as)($hr)->post("{$this->base}/recruitment/jobs", ['title' => 'Sales Rep', 'employment_type' => 'full_time', 'positions' => 2, 'status' => 'open'])->assertRedirect();
    $job = JobOpening::where('tenant_id', $this->tid)->firstOrFail();

    ($this->as)($hr)->post("{$this->base}/recruitment/candidates", ['job_opening_id' => $job->id, 'name' => 'Sara Adel Hassan', 'email' => 'sara@example.test'])->assertRedirect();
    $c = Candidate::where('tenant_id', $this->tid)->firstOrFail();
    expect($c->stage)->toBe('applied');

    foreach (['screening', 'interview', 'offer'] as $stage) {
        ($this->as)($hr)->post("{$this->base}/recruitment/candidates/{$c->id}/move", ['stage' => $stage])->assertRedirect();
    }
    ($this->as)($hr)->post("{$this->base}/recruitment/candidates/{$c->id}/move", ['stage' => 'hired'])->assertSessionHasErrors('stage');

    ($this->as)($hr)->post("{$this->base}/recruitment/candidates/{$c->id}/hire")->assertRedirect();
    $c->refresh();
    expect($c->stage)->toBe('hired')->and($c->employee_id)->not->toBeNull();

    // hired candidates cannot be deleted, openings with candidates cannot be deleted
    ($this->as)($hr)->delete("{$this->base}/recruitment/candidates/{$c->id}")->assertSessionHasErrors('candidate');
    ($this->as)($hr)->delete("{$this->base}/recruitment/jobs/{$job->id}")->assertSessionHasErrors('job');
    expect($c->events()->count())->toBe(5);

    ($this->as)($hr)->get("{$this->base}/recruitment")->assertOk();
});

test('only hr roles can open recruitment and hiring needs employee management', function () {
    $manager = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-rec@tenant-test.example');
    $employee = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-rec@tenant-test.example');
    $job = recruitJob($this);

    foreach ([$manager, $employee] as $u) {
        ($this->as)($u)->get("{$this->base}/recruitment")->assertForbidden();
        ($this->as)($u)->post("{$this->base}/recruitment/candidates", ['job_opening_id' => $job->id, 'name' => 'X'])->assertForbidden();
    }
});

test('recruitment records stay inside their company', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-rec2@tenant-test.example');
    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Other Rec Co', adminName: 'O', email: 'o@other-rec.example', password: 'StrongPassword123!');
    $foreign = JobOpening::create(['tenant_id' => (string) $other['tenant']->id, 'title' => 'Secret', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
    $theirCandidate = Candidate::create(['tenant_id' => (string) $other['tenant']->id, 'job_opening_id' => $foreign->id, 'name' => 'Them', 'stage' => 'applied']);

    ($this->as)($hr)->post("{$this->base}/recruitment/candidates", ['job_opening_id' => $foreign->id, 'name' => 'Sneaky'])->assertNotFound();
    ($this->as)($hr)->put("{$this->base}/recruitment/jobs/{$foreign->id}", ['title' => 'x', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open'])->assertNotFound();
    ($this->as)($hr)->post("{$this->base}/recruitment/candidates/{$theirCandidate->id}/move", ['stage' => 'rejected'])->assertNotFound();
    ($this->as)($hr)->post("{$this->base}/recruitment/jobs", ['title' => 'Y', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open', 'department_id' => 99999])->assertSessionHasErrors('department_id');
});
