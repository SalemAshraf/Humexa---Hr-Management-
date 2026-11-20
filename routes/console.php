<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('humexa:seed-demo {tenant : The tenant ID to seed}', function (string $tenantId) {
    $tenant = \App\Models\Tenant::query()->findOrFail($tenantId);

    tenancy()->initialize($tenant);

    try {
        app(\Database\Seeders\TenantDemoDataSeeder::class)->run();

        $this->info('Demo organization and employee data seeded successfully.');
        $this->table(
            ['Resource', 'Count'],
            [
                ['Branches', \App\Modules\Organization\Models\Branch::query()->where('tenant_id', $tenantId)->count()],
                ['Departments', \App\Modules\Organization\Models\Department::query()->where('tenant_id', $tenantId)->count()],
                ['Positions', \App\Modules\Organization\Models\Position::query()->where('tenant_id', $tenantId)->count()],
                ['Employees', \App\Modules\Employees\Models\Employee::query()->where('tenant_id', $tenantId)->count()],
            ]
        );
    } finally {
        tenancy()->end();
    }
})->purpose('Seed demo organization and employee records for one tenant');
