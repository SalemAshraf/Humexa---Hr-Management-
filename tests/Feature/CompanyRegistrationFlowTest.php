<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('it displays the company registration page', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
        );
});

test('it registers a company through the HTTP endpoint', function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    $response = $this->from('/register')->post('/register', [
        'company_name' => 'Acme Company',
        'name' => 'John Admin',
        'email' => 'john@acme.com',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ]);

    $response->assertRedirect('/register');

    $response->assertSessionHas(
        'status',
        'Company registered successfully.'
    );

    $response->assertSessionHas(
        'registered_domain',
        'acme-company.humexa.test'
    );

    $this->assertDatabaseHas('domains', [
        'domain' => 'acme-company.humexa.test',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@acme.com',
        'account_type' => User::ACCOUNT_TYPE_TENANT,
    ]);

    $this->assertDatabaseCount('tenants', 1);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('domains', 1);
});
