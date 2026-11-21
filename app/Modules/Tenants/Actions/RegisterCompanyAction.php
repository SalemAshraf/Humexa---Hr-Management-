<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Actions;

use App\Models\Tenant;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Models\User;
use App\Modules\Users\Actions\ProvisionTenantRolesAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class RegisterCompanyAction
{
    /**
     * Register a company and its first administrator.
     *
     * @return array{
     *     tenant: Tenant,
     *     user: User,
     *     domain: string
     * }
     */
    public function execute(
        string $companyName,
        string $adminName,
        string $email,
        string $password
    ): array {
        return DB::transaction(function () use (
            $companyName,
            $adminName,
            $email,
            $password
        ) {
            $tenant = Tenant::create([
                'company_name' => trim($companyName),
            ]);

            // Provision the tenant's default attendance policy during onboarding.
            AttendancePolicy::create([
                'tenant_id' => $tenant->id,
            ]);

            $domain = $this->generateDomain($companyName);

            $tenant->createDomain($domain);

            $user = new User([
                'name' => trim($adminName),
                'email' => strtolower(trim($email)),
                'password' => $password,
            ]);

            // These values must only be assigned by trusted backend code.
            $user->forceFill([
                'tenant_id' => $tenant->id,
                'account_type' => User::ACCOUNT_TYPE_TENANT,
            ]);

            $user->save();

            app(ProvisionTenantRolesAction::class)->execute($tenant);

            $tenant->run(function () use ($user): void {
                $user->assignRole(TenantRoleCatalog::OWNER);
            });

            return [
                'tenant' => $tenant,
                'user' => $user,
                'domain' => $domain,
            ];
        }, 3);
    }

    private function generateDomain(string $companyName): string
    {
        $baseDomain = strtolower(
            trim((string) config('humexa.tenant_domain'))
        );

        if ($baseDomain === '') {
            throw new LogicException('Tenant base domain is not configured.');
        }

        $baseSlug = Str::slug($companyName);

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'company_name' => 'The company name cannot generate a valid subdomain.',
            ]);
        }

        $reserved = config('humexa.reserved_subdomains', []);

        if (in_array($baseSlug, $reserved, true)) {
            $baseSlug = 'company-' . $baseSlug;
        }

        $domainModel = config('tenancy.domain_model');

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            throw new LogicException('Invalid tenancy domain model configuration.');
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            $domainModel::query()
                ->where('domain', "{$slug}.{$baseDomain}")
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return "{$slug}.{$baseDomain}";
    }
}
