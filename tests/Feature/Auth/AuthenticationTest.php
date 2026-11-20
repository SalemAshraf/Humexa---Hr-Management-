<?php

declare(strict_types=1);

namespace Tests\\Feature\\Auth;

use App\\Models\\User;
use App\\Modules\\Tenants\\Actions\\RegisterCompanyAction;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'humexa.tenant_domain' => 'humexa.test',
            'humexa.reserved_subdomains' => [],
        ]);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_tenant_users_can_authenticate_using_the_central_login_screen(): void
    {
        ['user' => $user] = $this->createTenantAccount();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
        ]);

        $this->assertAuthenticatedAs($user, 'tenant');
        $this->assertGuest('web');
        $response->assertRedirect('http://company-alpha.humexa.test/dashboard');
    }

    public function test_tenant_users_can_not_authenticate_with_invalid_password(): void
    {
        ['user' => $user] = $this->createTenantAccount();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('tenant');
        $this->assertGuest('web');
    }

    public function test_tenant_users_can_logout_from_their_tenant_portal(): void
    {
        ['user' => $user] = $this->createTenantAccount();

        $this->actingAs($user, 'tenant');

        $response = $this->post('http://company-alpha.humexa.test/logout');

        $this->assertGuest('tenant');
        $response->assertRedirect('/login');
    }

    /**
     * Create a real tenant account using the same trusted application action
     * used by company registration. A generic User factory record is not a
     * valid tenant login account.
     *
     * @return array{tenant: \\App\\Models\\Tenant, user: User, domain: string}
     */
    private function createTenantAccount(): array
    {
        return app(RegisterCompanyAction::class)->execute(
            companyName: 'Company Alpha',
            adminName: 'Company Alpha Admin',
            email: 'admin@company-alpha.example',
            password: 'StrongPassword123!',
        );
    }
}
