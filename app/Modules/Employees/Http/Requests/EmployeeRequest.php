<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use App\Modules\Employees\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenancy()->initialized
            && $this->user('tenant') !== null
            && (string) $this->user('tenant')->tenant_id === (string) tenant()->getTenantKey();
    }

    protected function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    protected function employeeId(): ?string
    {
        $value = $this->route('employee');

        return $value === null ? null : (string) $value;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tenantId = $this->tenantId();
            $branchId = $this->input('branch_id');
            $departmentId = $this->input('department_id');
            $positionId = $this->input('position_id');
            $managerId = $this->input('manager_id');
            $userId = $this->input('user_id');

            if ($departmentId && $branchId) {
                $departmentBranchId = \App\Modules\Organization\Models\Department::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($departmentId)
                    ->value('branch_id');

                if ($departmentBranchId !== null && (string) $departmentBranchId !== (string) $branchId) {
                    $validator->errors()->add('department_id', 'The selected department does not belong to the selected branch.');
                }
            }

            if ($positionId && $departmentId) {
                $positionDepartmentId = \App\Modules\Organization\Models\Position::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($positionId)
                    ->value('department_id');

                if ((string) $positionDepartmentId !== (string) $departmentId) {
                    $validator->errors()->add('position_id', 'The selected position must belong to the selected department.');
                }
            }

            if ($managerId && $this->employeeId() !== null && (string) $managerId === $this->employeeId()) {
                $validator->errors()->add('manager_id', 'An employee cannot be their own manager.');
            }

            if ($userId) {
                $alreadyLinked = \App\Modules\Employees\Models\Employee::query()
                    ->where('user_id', $userId)
                    ->when($this->employeeId(), fn ($query, $id) => $query->whereKeyNot($id))
                    ->exists();

                if ($alreadyLinked) {
                    $validator->errors()->add('user_id', 'This account is already linked to another employee.');
                }
            }
        });
    }

    protected function commonRules(): array
    {
        $tenantId = $this->tenantId();
        $employeeId = $this->employeeId();

        return [
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('tenant_id', $tenantId)
                    ->ignore($employeeId),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('account_type', 'tenant'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'work_email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('employees', 'work_email')->where('tenant_id', $tenantId)->ignore($employeeId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'hire_date' => ['nullable', 'date', 'required_unless:employment_status,draft'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_status' => ['required', Rule::in(Employee::STATUSES)],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date', 'required_if:employment_status,resigned,terminated'],
            'termination_reason' => ['nullable', 'string', 'max:5000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($employeeId),
            ],
            'status_change_reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
