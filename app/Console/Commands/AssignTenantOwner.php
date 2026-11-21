<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Users\Actions\AssignTenantOwnerAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AssignTenantOwner extends Command
{
    protected $signature = 'app:assign-tenant-owner {tenant : Company (tenant) id} {email : Email of the account to make Owner} {--confirm : Confirm you verified this person owns the company}';

    protected $description = 'Assign the Owner role to a named account of a legacy company (operator-confirmed, never automatic)';

    public function handle(AssignTenantOwnerAction $action): int
    {
        if (! $this->option('confirm')) {
            $this->components->error('Refusing to run without --confirm. Verify the person owns this company first.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($this->argument('tenant'));

        if ($tenant === null) {
            $this->components->error('Company not found.');

            return self::FAILURE;
        }

        try {
            $action->execute($tenant, (string) $this->argument('email'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Owner role assigned.');

        return self::SUCCESS;
    }
}
