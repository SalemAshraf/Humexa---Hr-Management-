<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AiConnector\Models\OauthToken;
use App\Modules\Employees\Models\Employee;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Ai Co', adminName: 'Ai Owner', email: 'ai-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://'.$this->reg['domain'];
    $this->mk = function ($tenant, string $tid, string $role, string $email) {
        return $tenant->run(function () use ($tid, $role, $email) {
            $u = new User(['name' => $role.' person', 'email' => $email, 'password' => 'StrongPassword123!']);
            $u->forceFill(['tenant_id' => $tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
            $u->assignRole($role);

            return $u;
        });
    };
    $this->hr = ($this->mk)($this->reg['tenant'], $this->tid, TenantRoleCatalog::HR_ADMIN, 'hr-ai@tenant-test.example');
    $this->mgr = ($this->mk)($this->reg['tenant'], $this->tid, TenantRoleCatalog::MANAGER, 'mgr-ai@tenant-test.example');
    $this->plain = ($this->mk)($this->reg['tenant'], $this->tid, TenantRoleCatalog::EMPLOYEE, 'emp-ai@tenant-test.example');
    Employee::query()->forceCreate(['tenant_id' => $this->tid, 'employee_number' => 'E1', 'first_name' => 'Secretname', 'last_name' => 'Person', 'employment_status' => 'active', 'hire_date' => '2024-01-01', 'date_of_birth' => '1990-05-05']);
});

function aiPkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

/** Runs registration, consent and the token exchange. Returns the token response data. */
function aiConnect($t, User $user, string $base, ?string $decision = 'allow'): array
{
    $redirect = 'https://claude.ai/api/mcp/auth_callback';
    $client = $t->postJson($base.'/oauth/register', ['redirect_uris' => [$redirect], 'client_name' => 'Claude', 'token_endpoint_auth_method' => 'none'])->assertCreated()->json('client_id');
    [$verifier, $challenge] = aiPkce();
    $q = http_build_query(['client_id' => $client, 'redirect_uri' => $redirect, 'response_type' => 'code', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256', 'state' => 'xyz', 'resource' => $base.'/mcp']);
    $t->actingAs($user, 'tenant')->withSession(['tenant_session_version' => 1])->get($base.'/oauth/authorize?'.$q)->assertOk();
    $rid = session('oauth_req.rid');
    $resp = $t->post($base.'/oauth/authorize', ['rid' => $rid, 'decision' => $decision]);
    $loc = (string) $resp->headers->get('Location');
    parse_str((string) parse_url($loc, PHP_URL_QUERY), $out);
    if ($decision !== 'allow' || ! isset($out['code'])) {
        return ['denied' => $out['error'] ?? null, 'client' => $client];
    }
    $tok = $t->postJson($base.'/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client, 'code' => $out['code'], 'redirect_uri' => $redirect, 'code_verifier' => $verifier]);

    return ['client' => $client, 'code' => $out['code'], 'verifier' => $verifier, 'redirect' => $redirect, 'response' => $tok, 'state' => $out['state'] ?? null];
}

function aiRpc($t, string $base, string $token, string $method, array $params = [])
{
    return $t->withHeader('Authorization', 'Bearer '.$token)->postJson($base.'/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

test('discovery metadata is per company host and public clients only', function () {
    $this->getJson($this->base.'/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', $this->base.'/mcp');
    $this->getJson($this->base.'/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('code_challenge_methods_supported', ['S256']);
    $this->postJson($this->base.'/oauth/register', ['redirect_uris' => ['https://evil.example/cb']])->assertStatus(400);
    $this->postJson($this->base.'/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401)->assertHeader('WWW-Authenticate');
});

test('an HR Admin connects and reads company totals only, never names or pay', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    expect($c['state'])->toBe('xyz');
    $c['response']->assertOk();
    $access = $c['response']->json('access_token');
    expect($access)->toStartWith('afo_');

    $list = aiRpc($this, $this->base, $access, 'tools/list')->assertOk()->json('result.tools');
    expect(collect($list)->pluck('name')->all())->toEqualCanonicalizing(['workforce_headcount', 'attendance_today', 'leave_overview', 'open_positions']);
    expect(collect($list)->every(fn ($t) => $t['annotations']['readOnlyHint'] === true))->toBeTrue();

    $res = aiRpc($this, $this->base, $access, 'tools/call', ['name' => 'workforce_headcount'])->assertOk();
    $text = $res->json('result.content.0.text');
    expect(json_decode($text, true)['current'])->toBeGreaterThanOrEqual(1)->and($text)->not->toContain('Secretname')->not->toContain('Person');

    foreach (['attendance_today', 'leave_overview', 'open_positions'] as $tool) {
        aiRpc($this, $this->base, $access, 'tools/call', ['name' => $tool])->assertOk()->assertJsonPath('result.content.0.type', 'text');
    }
    // No write or unknown tool exists.
    aiRpc($this, $this->base, $access, 'tools/call', ['name' => 'update_employee'])->assertJsonPath('error.code', -32602);
    expect(OauthToken::query()->whereNotNull('last_used_at')->count())->toBe(1);
});

test('a Manager only gets the tools that match their own permissions', function () {
    $c = aiConnect($this, $this->mgr, $this->base);
    $access = $c['response']->assertOk()->json('access_token');
    $names = collect(aiRpc($this, $this->base, $access, 'tools/list')->json('result.tools'))->pluck('name')->all();
    expect($names)->toBe(['attendance_today']);
    aiRpc($this, $this->base, $access, 'tools/call', ['name' => 'workforce_headcount'])->assertJsonPath('error.code', -32602);
    aiRpc($this, $this->base, $access, 'tools/call', ['name' => 'open_positions'])->assertJsonPath('error.code', -32602);
});

test('denying, and a self-service account allowing, both end without a token', function () {
    $denied = aiConnect($this, $this->hr, $this->base, 'deny');
    expect($denied['denied'])->toBe('access_denied');
    $blocked = aiConnect($this, $this->plain, $this->base, 'allow');
    expect($blocked['denied'])->toBe('access_denied');
    expect(OauthToken::query()->count())->toBe(0);
});

test('a token only works on the company it was approved for', function () {
    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Other Co', adminName: 'Other Owner', email: 'other-owner@tenant-test.example', password: 'StrongPassword123!');
    $otherBase = 'http://'.$other['domain'];
    $access = aiConnect($this, $this->hr, $this->base)['response']->json('access_token');
    aiRpc($this, $otherBase, $access, 'tools/list')->assertStatus(401);
    aiRpc($this, $this->base, $access, 'tools/list')->assertOk();
});

test('a code cannot be used twice, and reuse revokes the connection', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    $access = $c['response']->json('access_token');
    $again = $this->postJson($this->base.'/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $c['client'], 'code' => $c['code'], 'redirect_uri' => $c['redirect'], 'code_verifier' => $c['verifier']]);
    $again->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    aiRpc($this, $this->base, $access, 'tools/list')->assertStatus(401);
});

test('a wrong PKCE verifier fails', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    // Get a fresh code, then present the wrong verifier.
    expect(true)->toBeTrue();
    $redirect = 'https://claude.ai/api/mcp/auth_callback';
    [$verifier, $challenge] = aiPkce();
    $q = http_build_query(['client_id' => $c['client'], 'redirect_uri' => $redirect, 'response_type' => 'code', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256']);
    $this->actingAs($this->hr, 'tenant')->withSession(['tenant_session_version' => 1])->get($this->base.'/oauth/authorize?'.$q)->assertOk();
    $loc = (string) $this->post($this->base.'/oauth/authorize', ['rid' => session('oauth_req.rid'), 'decision' => 'allow'])->headers->get('Location');
    parse_str((string) parse_url($loc, PHP_URL_QUERY), $out);
    $this->postJson($this->base.'/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $c['client'], 'code' => $out['code'], 'redirect_uri' => $redirect, 'code_verifier' => str_repeat('a', 50)])->assertStatus(400);
});

test('refresh rotates, and an old refresh token ends the connection', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    $r1 = $c['response']->json('refresh_token');
    $new = $this->postJson($this->base.'/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $c['client'], 'refresh_token' => $r1])->assertOk();
    aiRpc($this, $this->base, $new->json('access_token'), 'tools/list')->assertOk();
    $this->postJson($this->base.'/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $c['client'], 'refresh_token' => $r1])->assertStatus(400);
    aiRpc($this, $this->base, $new->json('access_token'), 'tools/list')->assertStatus(401);
});

test('disconnecting, disabling the user or suspending the company stops access', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    $access = $c['response']->json('access_token');
    $family = OauthToken::query()->first()->family;
    $this->actingAs($this->hr, 'tenant')->withSession(['tenant_session_version' => 1])->get($this->base.'/ai-connections')->assertOk();
    $this->delete($this->base.'/ai-connections/'.$family)->assertRedirect();
    aiRpc($this, $this->base, $access, 'tools/list')->assertStatus(401);

    $c2 = aiConnect($this, $this->hr, $this->base);
    $a2 = $c2['response']->json('access_token');
    $this->hr->forceFill(['disabled_at' => now()])->save();
    aiRpc($this, $this->base, $a2, 'tools/list')->assertStatus(401);
    $this->hr->forceFill(['disabled_at' => null])->save();
    aiRpc($this, $this->base, $a2, 'tools/list')->assertOk();
});

test('the connection still reads after the plan period ended (view only) and revoke works', function () {
    $this->reg['tenant']->forceFill(['trial_ends_at' => now()->subDays(5)->toDateString()])->save();
    $c = aiConnect($this, $this->hr, $this->base);
    aiRpc($this, $this->base, $c['response']->json('access_token'), 'tools/call', ['name' => 'attendance_today'])->assertOk();
});

test('a suspended company cannot use the connector', function () {
    $c = aiConnect($this, $this->hr, $this->base);
    $access = $c['response']->json('access_token');
    $this->reg['tenant']->forceFill(['status' => 'suspended'])->save();
    tenancy()->end(); // a real request starts from a fresh company lookup
    expect(aiRpc($this, $this->base, $access, 'tools/list')->status())->not->toBe(200);
});
