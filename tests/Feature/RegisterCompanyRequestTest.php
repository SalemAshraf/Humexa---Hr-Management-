<?php

declare(strict_types=1);

use App\Http\Requests\RegisterCompanyRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
beforeEach(function () {
    Route::post('/_test/register-company', function (RegisterCompanyRequest $request) {
        return response()->json([
            'validated' => $request->validated(),
        ]);
    });
});

test('it validates company registration data', function () {
    $response = $this->postJson('/_test/register-company', [
        'company_name' => 'Acme Company',
        'name' => 'John Admin',
        'email' => 'john@example.com',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('validated.email', 'john@example.com');
});

test('it rejects invalid company registration data', function () {
    $response = $this->postJson('/_test/register-company', [
        'company_name' => '',
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
        'password_confirmation' => '456',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'company_name',
            'name',
            'email',
            'password',
        ]);
});

test('it normalizes email before validation', function () {
    $response = $this->postJson('/_test/register-company', [
        'company_name' => 'Acme Company',
        'name' => 'John Admin',
        'email' => '  JOHN@EXAMPLE.COM  ',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('validated.email', 'john@example.com');
});
