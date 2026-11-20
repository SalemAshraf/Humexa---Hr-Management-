<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Database\Seeder;
use RuntimeException;

class OrganizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = trim((string) env('HUMEXA_DEMO_TENANT_ID'));

        if ($tenantId === '') {
            throw new RuntimeException(
                'Set HUMEXA_DEMO_TENANT_ID in .env to the exact tenant ID before running this seeder.'
            );
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            throw new RuntimeException("Demo tenant [{$tenantId}] was not found.");
        }

        tenancy()->initialize($tenant);

        try {
            $tenantId = (string) $tenant->getTenantKey();

            $branches = [
                ['code' => 'ALX-001', 'name' => 'Alexandria HQ', 'city' => 'Alexandria', 'address' => 'Smouha, Alexandria'],
                ['code' => 'CAI-001', 'name' => 'Cairo Branch', 'city' => 'Cairo', 'address' => 'Nasr City, Cairo'],
                ['code' => 'GIZ-001', 'name' => 'Giza Branch', 'city' => 'Giza', 'address' => 'Sheikh Zayed, Giza'],
                ['code' => 'MAN-001', 'name' => 'Mansoura Branch', 'city' => 'Mansoura', 'address' => 'El Gomhoria Street, Mansoura'],
            ];

            $branchIds = [];

            foreach ($branches as $data) {
                $branch = Branch::query()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->first();

                $branch ??= Branch::query()->forceCreate([
                    ...$data,
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ]);

                $branchIds[$data['code']] = $branch->id;
            }

            $departments = [
                ['code' => 'HR', 'name' => 'Human Resources', 'branch' => 'ALX-001'],
                ['code' => 'FIN', 'name' => 'Finance', 'branch' => 'ALX-001'],
                ['code' => 'ENG', 'name' => 'Engineering', 'branch' => 'ALX-001'],
                ['code' => 'MKT', 'name' => 'Marketing', 'branch' => 'CAI-001'],
                ['code' => 'SAL', 'name' => 'Sales', 'branch' => 'CAI-001'],
                ['code' => 'CS', 'name' => 'Customer Support', 'branch' => 'GIZ-001'],
                ['code' => 'OPS', 'name' => 'Operations', 'branch' => 'MAN-001'],
                ['code' => 'IT', 'name' => 'IT', 'branch' => null],
            ];

            $departmentIds = [];

            foreach ($departments as $data) {
                $department = Department::query()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->first();

                $department ??= Department::query()->forceCreate([
                    'tenant_id' => $tenantId,
                    'branch_id' => $data['branch'] ? $branchIds[$data['branch']] : null,
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'is_active' => true,
                ]);

                $departmentIds[$data['code']] = $department->id;
            }

            $positions = [
                ['code' => 'HR-MGR', 'name' => 'HR Manager', 'department' => 'HR'],
                ['code' => 'HR-SPC', 'name' => 'HR Specialist', 'department' => 'HR'],
                ['code' => 'FIN-ACC', 'name' => 'Accountant', 'department' => 'FIN'],
                ['code' => 'FIN-MGR', 'name' => 'Finance Manager', 'department' => 'FIN'],
                ['code' => 'ENG-SLD', 'name' => 'Senior Laravel Developer', 'department' => 'ENG'],
                ['code' => 'ENG-FED', 'name' => 'Frontend Developer', 'department' => 'ENG'],
                ['code' => 'MKT-UX', 'name' => 'UI/UX Designer', 'department' => 'MKT'],
                ['code' => 'MKT-SP', 'name' => 'Marketing Specialist', 'department' => 'MKT'],
                ['code' => 'SAL-REP', 'name' => 'Sales Representative', 'department' => 'SAL'],
                ['code' => 'CS-AGT', 'name' => 'Customer Support Agent', 'department' => 'CS'],
                ['code' => 'OPS-SUP', 'name' => 'Operations Supervisor', 'department' => 'OPS'],
                ['code' => 'IT-ADM', 'name' => 'IT Administrator', 'department' => 'IT'],
            ];

            foreach ($positions as $data) {
                $exists = Position::query()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->exists();

                if (! $exists) {
                    Position::query()->forceCreate([
                        'tenant_id' => $tenantId,
                        'department_id' => $departmentIds[$data['department']],
                        'name' => $data['name'],
                        'code' => $data['code'],
                        'is_active' => true,
                    ]);
                }
            }
        } finally {
            tenancy()->end();
        }
    }
}
