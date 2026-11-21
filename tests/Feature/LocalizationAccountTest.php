<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Lang Co', adminName: 'Lang Owner', email: 'lang-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
});

test('English is the default and sends no translations', function () {
    $owner = $this->reg['user'];
    ($this->as)($owner)->get("{$this->base}/dashboard")->assertOk()
        ->assertInertia(fn ($p) => $p->where('locale', 'en')->where('dir', 'ltr')->where('translations', []));
});

test('the language switch stores the choice, flips direction and sends Arabic strings', function () {
    $owner = $this->reg['user'];
    ($this->as)($owner)->post("{$this->base}/locale", ['locale' => 'ar'])->assertRedirect()->assertCookie('locale');
    expect($this->reg['tenant']->run(fn () => $owner->fresh()->locale))->toBe('ar');

    ($this->as)($owner)->withUnencryptedCookie('locale', 'ar')->get("{$this->base}/dashboard")->assertOk()
        ->assertInertia(fn ($p) => $p->where('locale', 'ar')->where('dir', 'rtl')->where('translations.Welcome back', 'أهلًا بعودتك'));
    ($this->as)($owner)->withUnencryptedCookie('locale', 'ar')->get("{$this->base}/dashboard")->assertSee('dir="rtl"', false);

    // an unsupported value is refused and an unknown cookie falls back to English
    ($this->as)($owner)->post("{$this->base}/locale", ['locale' => 'xx'])->assertSessionHasErrors('locale');
    ($this->as)($owner)->withUnencryptedCookie('locale', 'xx')->get("{$this->base}/dashboard")->assertInertia(fn ($p) => $p->where('locale', 'en'));
});

test('login applies the saved language of the account', function () {
    $this->reg['tenant']->run(fn () => $this->reg['user']->forceFill(['locale' => 'ar'])->save());
    $this->post("{$this->base}/login", ['email' => 'lang-owner@tenant-test.example', 'password' => 'StrongPassword123!'])->assertRedirect()->assertCookie('locale');
});

test('My account is open to every role including Owner and edits only the own name and language', function () {
    $owner = $this->reg['user'];
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-acc@tenant-test.example');
    $other = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'other-acc@tenant-test.example');

    ($this->as)($owner)->get("{$this->base}/account")->assertOk()->assertInertia(fn ($p) => $p->where('account.email', 'lang-owner@tenant-test.example'));
    ($this->as)($emp)->get("{$this->base}/account")->assertOk();

    // tries to smuggle in another account, an email change and a role: all ignored
    ($this->as)($emp)->put("{$this->base}/account", ['name' => 'Renamed Emp', 'locale' => 'ar', 'email' => 'hijack@x.test', 'id' => $other->id, 'user_id' => $other->id, 'role' => 'Owner'])->assertSessionHasNoErrors();
    $state = $this->reg['tenant']->run(fn () => [
        'emp' => $emp->fresh()->only(['name', 'email', 'locale']),
        'other' => $other->fresh()->only(['name', 'email', 'locale']),
        'role' => $emp->fresh()->getRoleNames()->all(),
    ]);
    expect($state['emp'])->toBe(['name' => 'Renamed Emp', 'email' => 'emp-acc@tenant-test.example', 'locale' => 'ar'])
        ->and($state['other']['name'])->toBe('other-acc@tenant-test.example')
        ->and($state['other']['locale'])->toBe('en')
        ->and($state['role'])->toBe([TenantRoleCatalog::EMPLOYEE]);

    ($this->as)($emp)->put("{$this->base}/account", ['name' => 'x', 'locale' => 'en'])->assertSessionHasErrors('name');
    ($this->as)($emp)->put("{$this->base}/account", ['name' => 'Fine Name', 'locale' => 'fr'])->assertSessionHasErrors('locale');
});
