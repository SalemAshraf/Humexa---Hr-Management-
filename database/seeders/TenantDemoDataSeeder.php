<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class TenantDemoDataSeeder extends Seeder
{
    /**
     * Seed demo organization and employee records for the initialized tenant.
     *
     * This seeder is intentionally tenant-context-only and idempotent.
     */
    public function run(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Initialize a tenant before running TenantDemoDataSeeder.');
        }

        $tenantId = (string) tenant()->getTenantKey();

        DB::transaction(function () use ($tenantId): void {
            $branches = $this->seedBranches($tenantId);
            $departments = $this->seedDepartments($tenantId, $branches);
            $positions = $this->seedPositions($tenantId, $departments);
            $this->seedEmployees($tenantId, $departments, $positions);
        }, 3);
    }

    private function seedBranches(string $tenantId): array
    {
        $definitions = [
            'ALEX-HQ' => [
                'name' => 'Alexandria Head Office',
                'city' => 'Alexandria',
                'address' => 'Smouha, Alexandria, Egypt',
            ],
            'CAI-OFFICE' => [
                'name' => 'Cairo Office',
                'city' => 'Cairo',
                'address' => 'New Cairo, Cairo, Egypt',
            ],
            'MNS-BRANCH' => [
                'name' => 'Mansoura Branch',
                'city' => 'Mansoura',
                'address' => 'Mansoura, Dakahlia, Egypt',
            ],
        ];

        $result = [];

        foreach ($definitions as $code => $attributes) {
            $branch = Branch::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();

            if (! $branch) {
                $branch = new Branch();
                $branch->forceFill([
                    ...$attributes,
                    'tenant_id' => $tenantId,
                    'code' => $code,
                    'is_active' => true,
                ])->save();
            } else {
                $branch->forceFill([...$attributes, 'is_active' => true])->save();
                if ($branch->trashed()) {
                    $branch->restore();
                }
            }

            $result[$code] = $branch;
        }

        return $result;
    }

    private function seedDepartments(string $tenantId, array $branches): array
    {
        $definitions = [
            'EXEC' => ['branch' => 'ALEX-HQ', 'name' => 'Executive Office', 'description' => 'Company leadership and strategic direction.'],
            'ENG' => ['branch' => 'ALEX-HQ', 'name' => 'Engineering', 'description' => 'Backend, frontend, infrastructure, and quality engineering.'],
            'PRODUCT' => ['branch' => 'ALEX-HQ', 'name' => 'Product & Design', 'description' => 'Product management and user experience design.'],
            'PEOPLE' => ['branch' => 'ALEX-HQ', 'name' => 'People & Culture', 'description' => 'Human resources, recruitment, and employee experience.'],
            'FINANCE' => ['branch' => 'ALEX-HQ', 'name' => 'Finance', 'description' => 'Accounting, budgeting, and financial operations.'],
            'MKT' => ['branch' => 'ALEX-HQ', 'name' => 'Marketing', 'description' => 'Brand, content, and digital marketing.'],
            'SALES' => ['branch' => 'CAI-OFFICE', 'name' => 'Sales', 'description' => 'Business development and account management.'],
            'SUPPORT' => ['branch' => 'CAI-OFFICE', 'name' => 'Customer Support', 'description' => 'Customer care and technical support.'],
            'OPS' => ['branch' => 'CAI-OFFICE', 'name' => 'Operations', 'description' => 'Daily business and service operations.'],
            'FIELD' => ['branch' => 'MNS-BRANCH', 'name' => 'Field Operations', 'description' => 'On-site service delivery and field coordination.'],
        ];

        $result = [];

        foreach ($definitions as $code => $attributes) {
            $department = Department::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();

            $values = [
                'branch_id' => $branches[$attributes['branch']]->id,
                'name' => $attributes['name'],
                'description' => $attributes['description'],
                'code' => $code,
                'is_active' => true,
            ];

            if (! $department) {
                $department = new Department();
                $department->forceFill([...$values, 'tenant_id' => $tenantId])->save();
            } else {
                $department->forceFill($values)->save();
                if ($department->trashed()) {
                    $department->restore();
                }
            }

            $result[$code] = $department;
        }

        return $result;
    }

    private function seedPositions(string $tenantId, array $departments): array
    {
        $definitions = [
            'CEO' => ['department' => 'EXEC', 'name' => 'Chief Executive Officer'],
            'ENG-MGR' => ['department' => 'ENG', 'name' => 'Engineering Manager'],
            'LARAVEL-DEV' => ['department' => 'ENG', 'name' => 'Senior Laravel Developer'],
            'VUE-DEV' => ['department' => 'ENG', 'name' => 'Frontend Developer'],
            'QA' => ['department' => 'ENG', 'name' => 'Quality Assurance Engineer'],
            'PRODUCT-MGR' => ['department' => 'PRODUCT', 'name' => 'Product Manager'],
            'UIUX' => ['department' => 'PRODUCT', 'name' => 'UI/UX Designer'],
            'HR-MGR' => ['department' => 'PEOPLE', 'name' => 'HR Manager'],
            'HR-SPECIALIST' => ['department' => 'PEOPLE', 'name' => 'HR Specialist'],
            'FIN-MGR' => ['department' => 'FINANCE', 'name' => 'Finance Manager'],
            'ACCOUNTANT' => ['department' => 'FINANCE', 'name' => 'Accountant'],
            'SALES-MGR' => ['department' => 'SALES', 'name' => 'Sales Manager'],
            'ACCOUNT-EXEC' => ['department' => 'SALES', 'name' => 'Account Executive'],
            'SUPPORT-SPEC' => ['department' => 'SUPPORT', 'name' => 'Customer Support Specialist'],
            'OPS-MGR' => ['department' => 'OPS', 'name' => 'Operations Manager'],
            'FIELD-SUP' => ['department' => 'FIELD', 'name' => 'Field Operations Supervisor'],
        ];

        $result = [];

        foreach ($definitions as $code => $attributes) {
            $position = Position::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();

            $values = [
                'department_id' => $departments[$attributes['department']]->id,
                'name' => $attributes['name'],
                'description' => $attributes['name'] . ' — demo position.',
                'code' => $code,
                'is_active' => true,
            ];

            if (! $position) {
                $position = new Position();
                $position->forceFill([...$values, 'tenant_id' => $tenantId])->save();
            } else {
                $position->forceFill($values)->save();
                if ($position->trashed()) {
                    $position->restore();
                }
            }

            $result[$code] = $position;
        }

        return $result;
    }

    private function seedEmployees(string $tenantId, array $departments, array $positions): void
    {
        $employees = [
            'DEMO-0001' => ['first_name' => 'Omar', 'middle_name' => 'Hassan', 'last_name' => 'Ibrahim', 'gender' => 'male', 'date_of_birth' => '1985-03-12', 'nationality' => 'Egyptian', 'marital_status' => 'married', 'work_email' => 'omar.ibrahim@example.test', 'phone' => '+201000000101', 'branch' => 'ALEX-HQ', 'department' => 'EXEC', 'position' => 'CEO', 'status' => 'active', 'hire_date' => '2020-01-05', 'manager' => null],
            'DEMO-0002' => ['first_name' => 'Mariam', 'middle_name' => 'Ahmed', 'last_name' => 'Fathy', 'gender' => 'female', 'date_of_birth' => '1990-07-21', 'nationality' => 'Egyptian', 'marital_status' => 'married', 'work_email' => 'mariam.fathy@example.test', 'phone' => '+201000000102', 'branch' => 'ALEX-HQ', 'department' => 'ENG', 'position' => 'ENG-MGR', 'status' => 'active', 'hire_date' => '2021-04-11', 'manager' => 'DEMO-0001'],
            'DEMO-0003' => ['first_name' => 'Youssef', 'middle_name' => 'Mahmoud', 'last_name' => 'Nassar', 'gender' => 'male', 'date_of_birth' => '1994-11-03', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'youssef.nassar@example.test', 'phone' => '+201000000103', 'branch' => 'ALEX-HQ', 'department' => 'ENG', 'position' => 'LARAVEL-DEV', 'status' => 'active', 'hire_date' => '2022-02-01', 'manager' => 'DEMO-0002'],
            'DEMO-0004' => ['first_name' => 'Nour', 'middle_name' => 'Tarek', 'last_name' => 'Salem', 'gender' => 'female', 'date_of_birth' => '1997-05-16', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'nour.salem@example.test', 'phone' => '+201000000104', 'branch' => 'ALEX-HQ', 'department' => 'ENG', 'position' => 'VUE-DEV', 'status' => 'probation', 'hire_date' => '2026-09-01', 'probation_end_date' => '2026-12-01', 'manager' => 'DEMO-0002'],
            'DEMO-0005' => ['first_name' => 'Karim', 'middle_name' => 'Samir', 'last_name' => 'Adel', 'gender' => 'male', 'date_of_birth' => '1992-01-27', 'nationality' => 'Egyptian', 'marital_status' => 'married', 'work_email' => 'karim.adel@example.test', 'phone' => '+201000000105', 'branch' => 'ALEX-HQ', 'department' => 'PEOPLE', 'position' => 'HR-MGR', 'status' => 'active', 'hire_date' => '2021-08-15', 'manager' => 'DEMO-0001'],
            'DEMO-0006' => ['first_name' => 'Salma', 'middle_name' => 'Hany', 'last_name' => 'Mostafa', 'gender' => 'female', 'date_of_birth' => '1998-09-09', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'salma.mostafa@example.test', 'phone' => '+201000000106', 'branch' => 'ALEX-HQ', 'department' => 'PEOPLE', 'position' => 'HR-SPECIALIST', 'status' => 'active', 'hire_date' => '2023-03-20', 'manager' => 'DEMO-0005'],
            'DEMO-0007' => ['first_name' => 'Amr', 'middle_name' => 'Khaled', 'last_name' => 'Ragab', 'gender' => 'male', 'date_of_birth' => '1989-12-02', 'nationality' => 'Egyptian', 'marital_status' => 'married', 'work_email' => 'amr.ragab@example.test', 'phone' => '+201000000107', 'branch' => 'CAI-OFFICE', 'department' => 'SALES', 'position' => 'SALES-MGR', 'status' => 'active', 'hire_date' => '2020-06-01', 'manager' => 'DEMO-0001'],
            'DEMO-0008' => ['first_name' => 'Hana', 'middle_name' => 'Ayman', 'last_name' => 'Farouk', 'gender' => 'female', 'date_of_birth' => '1999-04-18', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'hana.farouk@example.test', 'phone' => '+201000000108', 'branch' => 'CAI-OFFICE', 'department' => 'SALES', 'position' => 'ACCOUNT-EXEC', 'status' => 'probation', 'hire_date' => '2026-10-01', 'probation_end_date' => '2027-01-01', 'manager' => 'DEMO-0007'],
            'DEMO-0009' => ['first_name' => 'Mostafa', 'middle_name' => 'Wael', 'last_name' => 'Mahmoud', 'gender' => 'male', 'date_of_birth' => '1995-02-14', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'mostafa.mahmoud@example.test', 'phone' => '+201000000109', 'branch' => 'CAI-OFFICE', 'department' => 'SUPPORT', 'position' => 'SUPPORT-SPEC', 'status' => 'active', 'hire_date' => '2022-10-10', 'manager' => 'DEMO-0007'],
            'DEMO-0010' => ['first_name' => 'Dina', 'middle_name' => 'Ashraf', 'last_name' => 'Kamal', 'gender' => 'female', 'date_of_birth' => '1993-06-30', 'nationality' => 'Egyptian', 'marital_status' => 'married', 'work_email' => 'dina.kamal@example.test', 'phone' => '+201000000110', 'branch' => 'ALEX-HQ', 'department' => 'FINANCE', 'position' => 'ACCOUNTANT', 'status' => 'on_leave', 'hire_date' => '2021-11-01', 'manager' => 'DEMO-0001'],
            'DEMO-0011' => ['first_name' => 'Ziad', 'middle_name' => 'Hesham', 'last_name' => 'Ezzat', 'gender' => 'male', 'date_of_birth' => '1996-08-22', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'ziad.ezzat@example.test', 'phone' => '+201000000111', 'branch' => 'ALEX-HQ', 'department' => 'PRODUCT', 'position' => 'UIUX', 'status' => 'active', 'hire_date' => '2024-02-12', 'manager' => 'DEMO-0001'],
            'DEMO-0012' => ['first_name' => 'Nada', 'middle_name' => 'Sherif', 'last_name' => 'Fouad', 'gender' => 'female', 'date_of_birth' => '1997-10-05', 'nationality' => 'Egyptian', 'marital_status' => 'single', 'work_email' => 'nada.fouad@example.test', 'phone' => '+201000000112', 'branch' => 'MNS-BRANCH', 'department' => 'FIELD', 'position' => 'FIELD-SUP', 'status' => 'active', 'hire_date' => '2023-07-01', 'manager' => 'DEMO-0001'],
        ];

        $employeeIds = [];

        foreach ($employees as $employeeNumber => $data) {
            $existing = Employee::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('employee_number', $employeeNumber)
                ->first();

            if ($existing) {
                if (! $existing->trashed()) {
                    $employeeIds[$employeeNumber] = $existing->id;
                }

                continue;
            }

            $managerId = $data['manager'] ? ($employeeIds[$data['manager']] ?? null) : null;

            $payload = [
                'employee_number' => $employeeNumber,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'work_email' => $data['work_email'],
                'phone' => $data['phone'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'nationality' => $data['nationality'],
                'marital_status' => $data['marital_status'],
                'hire_date' => $data['hire_date'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'employment_status' => $data['status'],
                'branch_id' => $this->branchIdFor($data['branch'], $departments),
                'department_id' => $departments[$data['department']]->id,
                'position_id' => $positions[$data['position']]->id,
                'manager_id' => $managerId,
            ];

            $employee = app(CreateEmployeeAction::class)->execute($payload, $tenantId, null);
            $employeeIds[$employeeNumber] = $employee->id;
        }
    }

    private function branchIdFor(string $branchCode, array $departments): int
    {
        $department = collect($departments)->first(
            fn (Department $item) => $item->code === $this->departmentCodeForBranch($branchCode, $departments)
        );

        if (! $department) {
            throw new LogicException('Unable to resolve demo employee branch.');
        }

        return (int) $department->branch_id;
    }

    private function departmentCodeForBranch(string $branchCode, array $departments): string
    {
        foreach ($departments as $code => $department) {
            if ($branchCode === 'ALEX-HQ' && $code === 'EXEC') return $code;
            if ($branchCode === 'CAI-OFFICE' && $code === 'SALES') return $code;
            if ($branchCode === 'MNS-BRANCH' && $code === 'FIELD') return $code;
        }

        throw new LogicException('Unable to resolve demo employee branch code.');
    }
}
