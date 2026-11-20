<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Actions\UpdateEmployeeAction;
use App\Modules\Employees\Http\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    private function employeeQuery()
    {
        return Employee::query()->where('tenant_id', $this->tenantId());
    }

    private function formOptions(): array
    {
        $tenantId = $this->tenantId();

        return [
            'branches' => Branch::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where('is_active', true)->with('branch:id,name')->orderBy('name')->get(['id', 'branch_id', 'name']),
            'positions' => Position::query()->where('tenant_id', $tenantId)->where('is_active', true)->with('department:id,name')->orderBy('name')->get(['id', 'department_id', 'name']),
            'managers' => $this->employeeQuery()->whereNotIn('employment_status', [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_number']),
            'accounts' => User::query()
                ->where('tenant_id', $tenantId)
                ->where('account_type', User::ACCOUNT_TYPE_TENANT)
                ->whereDoesntHave('employee')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'statuses' => collect(Employee::STATUSES)->map(fn (string $status) => ['value' => $status, 'label' => str($status)->replace('_', ' ')->title()->toString()])->values(),
        ];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->employeeQuery()->with(['branch:id,name', 'department:id,name', 'position:id,name']);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            abort_unless(in_array($status, Employee::STATUSES, true), 422);
            $query->where('employment_status', $status);
        }

        return Inertia::render('Employees/Index', [
            'employees' => $query->latest('id')->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status'),
            'summary' => [
                'total' => $this->employeeQuery()->count(),
                'active' => $this->employeeQuery()->where('employment_status', Employee::STATUS_ACTIVE)->count(),
                'probation' => $this->employeeQuery()->where('employment_status', Employee::STATUS_PROBATION)->count(),
                'on_leave' => $this->employeeQuery()->where('employment_status', Employee::STATUS_ON_LEAVE)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Employees/Create', $this->formOptions());
    }

    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $action->execute(
            $request->validated(),
            $this->tenantId(),
            $request->user('tenant')?->getKey()
        );

        return redirect()->route('employees.show', $employee->id)->with('success', 'Employee created successfully.');
    }

    public function show(string $employee): Response
    {
        $record = $this->employeeQuery()
            ->with([
                'user:id,name,email',
                'branch:id,name',
                'department:id,name',
                'position:id,name',
                'manager:id,first_name,last_name,employee_number',
                'employmentHistories.changedBy:id,name',
            ])
            ->findOrFail($employee);

        $this->authorize('view', $record);

        return Inertia::render('Employees/Show', ['employee' => $record]);
    }

    public function edit(string $employee): Response
    {
        $record = $this->employeeQuery()->findOrFail($employee);
        $this->authorize('update', $record);

        return Inertia::render('Employees/Edit', [
            ...$this->formOptions(),
            'employee' => $record,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, string $employee, UpdateEmployeeAction $action): RedirectResponse
    {
        $record = $this->employeeQuery()->findOrFail($employee);
        $this->authorize('update', $record);

        $action->execute(
            $record,
            $request->validated(),
            $request->user('tenant')?->getKey()
        );

        return redirect()->route('employees.show', $record->id)->with('success', 'Employee updated successfully.');
    }

    public function destroy(string $employee): RedirectResponse
    {
        $record = $this->employeeQuery()->findOrFail($employee);
        $this->authorize('delete', $record);

        abort_if(
            $record->directReports()->exists(),
            409,
            'Reassign this employee direct reports before archiving the employee.'
        );

        $record->delete();

        return redirect()->route('employees.index')->with('success', 'Employee archived successfully.');
    }
}
