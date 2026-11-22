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
use Illuminate\Validation\Rule;

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

    private function formOptions(?Employee $employee = null): array
    {
        $tenantId = $this->tenantId();

        return [
            'branches' => Branch::query()->where('tenant_id', $tenantId)->where(function ($query) use ($employee) { $query->where('is_active', true)->when($employee?->branch_id, fn ($query, $id) => $query->orWhereKey($id)); })->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('tenant_id', $tenantId)->where(function ($query) use ($employee) { $query->where('is_active', true)->when($employee?->department_id, fn ($query, $id) => $query->orWhereKey($id)); })->with('branch:id,name')->orderBy('name')->get(['id', 'branch_id', 'name']),
            'positions' => Position::query()->where('tenant_id', $tenantId)->where(function ($query) use ($employee) { $query->where('is_active', true)->when($employee?->position_id, fn ($query, $id) => $query->orWhereKey($id)); })->with('department:id,name')->orderBy('name')->get(['id', 'department_id', 'name']),
            'managers' => $this->employeeQuery()
                ->where(function ($query) use ($employee) {
                    $query->whereNotIn('employment_status', [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])
                        ->when($employee?->manager_id, fn ($query, $id) => $query->orWhereKey($id));
                })
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_number']),
            'accounts' => User::query()
                ->where('tenant_id', $tenantId)
                ->where('account_type', User::ACCOUNT_TYPE_TENANT)
                ->whereNotIn('id', Employee::withTrashed()->where('tenant_id', $tenantId)->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'statuses' => collect(Employee::STATUSES)
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => str($status)->replace('_', ' ')->title()->toString(),
                ])->values(),
        ];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $query = $this->employeeQuery()->with([
            'branch:id,name',
            'department:id,name',
            'position:id,name',
        ]);

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

        $branchId = $request->query('branch_id');
        if ($branchId !== null && $branchId !== '') {
            $request->validate([
                'branch_id' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $this->tenantId())],
            ]);
            $query->where('branch_id', $branchId);
        }

        $departmentId = $request->query('department_id');
        if ($departmentId !== null && $departmentId !== '') {
            $request->validate([
                'department_id' => ['integer', Rule::exists('departments', 'id')->where('tenant_id', $this->tenantId())],
            ]);
            $query->where('department_id', $departmentId);
        }

        return Inertia::render('Employees/Index', [
            'employees' => $query->latest('id')->paginate(15)->withQueryString(),
            'branches' => Branch::query()->where('tenant_id', $this->tenantId())->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('tenant_id', $this->tenantId())->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'branch_id', 'department_id'),
            'summary' => [
                'total' => $this->employeeQuery()->count(),
                'active' => $this->employeeQuery()->where('employment_status', Employee::STATUS_ACTIVE)->count(),
                'probation' => $this->employeeQuery()->where('employment_status', Employee::STATUS_PROBATION)->count(),
                'on_leave' => $this->employeeQuery()->where('employment_status', Employee::STATUS_ON_LEAVE)->count(),
            ],
        ]);
    }

    public function archived(): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::withTrashed()
            ->where('tenant_id', $this->tenantId())
            ->whereNotNull('deleted_at')
            ->with(['branch:id,name', 'department:id,name', 'position:id,name'])
            ->latest('deleted_at')
            ->paginate(15);

        return Inertia::render('Employees/Archived', [
            'employees' => $employees,
        ]);
    }

    public function restore(string $tenant, string $employee): RedirectResponse
    {
        $record = Employee::withTrashed()
            ->where('tenant_id', $this->tenantId())
            ->whereNotNull('deleted_at')
            ->findOrFail($employee);

        $this->authorize('restore', $record);
        app(\App\Modules\Plans\Services\PlanLimits::class)->assertCanAddEmployee($this->tenantId());
        $record->restore();

        return redirect('/employees/archived')->with('success', 'Employee restored successfully.');
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

        return redirect('/employees/' . $employee->id)->with('success', 'Employee created successfully.');
    }

    public function show(Request $request): Response
    {
        $employee = (string) request()->route('employee');
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

        return Inertia::render('Employees/Show', [
            'employee' => $record,
            'hasPhoto' => filled($record->profile_photo_path),
            'canUpdate' => ($request->user('tenant')?->can('update', $record) ?? false) && $request->user('tenant')->can('employees.manage'),
        ]);
    }

    public function edit(): Response
    {
        $employee = (string) request()->route('employee');
        $record = $this->employeeQuery()->with(['user:id,name,email'])->findOrFail($employee);
        $this->authorize('update', $record);

        return Inertia::render('Employees/Edit', [
            ...$this->formOptions($record),
            'employee' => $record,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, UpdateEmployeeAction $action): RedirectResponse
    {
        $employee = (string) $request->route('employee');
        $record = $this->employeeQuery()->findOrFail($employee);
        $this->authorize('update', $record);

        $before = $record->user_id;

        $action->execute(
            $record,
            $request->validated(),
            $request->user('tenant')?->getKey()
        );

        // Changing which login belongs to an employee changes what both accounts can reach.
        // End their app tokens and open sessions so the change takes effect immediately.
        $after = $record->fresh()->user_id;
        if ((int) $before !== (int) $after) {
            foreach (array_filter([$before, $after]) as $uid) {
                $account = User::query()->where('tenant_id', (string) tenant()->getTenantKey())->find($uid);
                if ($account) {
                    $account->tokens()->delete();
                    if ((int) $uid !== (int) $request->user('tenant')?->getKey()) {
                        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $uid)->delete();
                    }
                }
            }
        }

        return redirect('/employees/' . $record->id)->with('success', 'Employee updated successfully.');
    }

    public function destroy(): RedirectResponse
    {
        $employee = (string) request()->route('employee');
        $record = $this->employeeQuery()->findOrFail($employee);
        $this->authorize('delete', $record);

        abort_if(
            $record->directReports()->exists(),
            409,
            'Reassign this employee direct reports before archiving the employee.'
        );

        $record->delete();

        return redirect('/employees')->with('success', 'Employee archived successfully.');
    }
}
