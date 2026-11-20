<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('app:create-platform-admin')]
#[Description('Create a platform administrator account securely')]
class CreatePlatformAdmin extends Command
{
    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->components->error(
                'Cannot create a platform administrator inside tenant context.'
            );

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Admin name'));
        $email = strtolower(trim((string) $this->ask('Admin email')));
        $password = (string) $this->secret('Admin password');
        $passwordConfirmation = (string) $this->secret(
            'Confirm admin password'
        );

        if ($password !== $passwordConfirmation) {
            $this->components->error('Passwords do not match.');

            return self::FAILURE;
        }

        try {
            Validator::make(
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => [
                        'required',
                        'string',
                        'email',
                        'max:255',
                        'unique:users,email',
                    ],
                    'password' => [
                        'required',
                        'string',
                        Password::min(12)->letters()->mixedCase()->numbers(),
                    ],
                ]
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        try {
            $admin = DB::transaction(function () use (
                $name,
                $email,
                $password
            ): User {
                $user = new User();

                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'account_type' => User::ACCOUNT_TYPE_PLATFORM,
                    'tenant_id' => null,
                    'email_verified_at' => now(),
                ]);

                $user->save();

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->components->error(
                'Failed to create platform administrator. Check the application logs.'
            );

            return self::FAILURE;
        }

        $this->components->info('Platform administrator created successfully.');

        $this->table(
            ['ID', 'Name', 'Email', 'Account Type', 'Tenant ID'],
            [[
                $admin->id,
                $admin->name,
                $admin->email,
                $admin->account_type,
                $admin->tenant_id ?? 'NULL',
            ]]
        );

        return self::SUCCESS;
    }
}
