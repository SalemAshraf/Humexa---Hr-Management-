<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
            );
    }

    public function test_company_admin_can_register_without_being_automatically_authenticated(): void
    {
        config([
            'humexa.tenant_domain' => 'humexa.test',
            'humexa.reserved_subdomains' => [],
        ]);

        $response = $this->from('/register')->post('/register', [
            'company_name' => 'Test Company',
            'name' => 'Test Admin',
            'email' => 'test@example.com',
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
            'test-company.humexa.test'
        );

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'account_type' => User::ACCOUNT_TYPE_TENANT,
        ]);

        $this->assertGuest();

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('domains', 1);
    }
}
