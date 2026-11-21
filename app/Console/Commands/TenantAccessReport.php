<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class TenantAccessReport extends Command
{
    protected $signature = 'app:tenant-access {domain : Exact company domain, e.g. reviv-solutions.humexa.test}';

    protected $description = 'Read-only: show a company id and its accounts with their roles. Changes nothing.';

    public function handle(): int
    {
        $domain = strtolower(trim((string) $this->argument('domain')));

        $domainModel = config('tenancy.domain_model');
        $row = $domainModel::query()->where('domain', $domain)->first();

        if ($row === null) {
            $this->components->error('No company has exactly this domain. Nothing was changed.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($row->tenant_id);

        if ($tenant === null) {
            $this->components->error('The domain points to a missing company. Nothing was changed.');

            return self::FAILURE;
        }

        $this->line('Company id: '.$tenant->getTenantKey().'   (domain: '.$domain.')');

        $users = User::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->orderBy('id')
            ->get();

        $rows = $tenant->run(fn () => $users->map(fn (User $u) => [
            $u->email,
            $u->getRoleNames()->implode(', ') ?: '(no role)',
        ])->all());

        $this->table(['Account email', 'Roles'], $rows);
        $this->line('To make one of these accounts the Owner (after you verify it is yours):');
        $this->line('php artisan app:assign-tenant-owner '.$tenant->getTenantKey().' <email> --confirm');

        return self::SUCCESS;
    }
}
