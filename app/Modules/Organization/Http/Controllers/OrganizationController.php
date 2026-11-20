<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\BranchRequest;
use App\Modules\Organization\Http\Requests\DepartmentRequest;
use App\Modules\Organization\Http\Requests\PositionRequest;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    public function index(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('Organization/Index', [
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->withCount(['departments' => fn ($query) => $query->where('tenant_id', $tenantId)])
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'city', 'address', 'is_active']),
            'departments' => Department::query()
                ->where('tenant_id', $tenantId)
                ->with(['branch:id,name'])
                ->withCount('positions')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'description', 'is_active']),
            'positions' => Position::query()
                ->where('tenant_id', $tenantId)
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'department_id', 'name', 'code', 'description', 'is_active']),
            'summary' => [
                'branches' => Branch::query()->where('tenant_id', $tenantId)->count(),
                'departments' => Department::query()->where('tenant_id', $tenantId)->count(),
                'positions' => Position::query()->where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }

    public function storeBranch(BranchRequest $request): RedirectResponse
    {
        Branch::query()->forceCreate([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
        ]);

        return back()->with('success', 'Branch created successfully.');
    }

    public function updateBranch(BranchRequest $request): RedirectResponse
    {
        $branch = (string) $request->route('branch');
        $record = Branch::query()->where('tenant_id', $this->tenantId())->findOrFail($branch);
        $record->update($request->validated());

        return back()->with('success', 'Branch updated successfully.');
    }

    public function destroyBranch(): RedirectResponse
    {
        $branch = (string) request()->route('branch');
        $tenantId = $this->tenantId();
        $record = Branch::query()->where('tenant_id', $tenantId)->findOrFail($branch);

        abort_if(
            Department::query()->where('tenant_id', $tenantId)->where('branch_id', $record->id)->withTrashed()->exists()
            || DB::table('employees')->where('tenant_id', $tenantId)->where('branch_id', $record->id)->exists(),
            409,
            'Reassign this branch departments and employees before archiving the branch.'
        );

        $record->delete();

        return back()->with('success', 'Branch archived successfully.');
    }

    public function storeDepartment(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->forceCreate([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
        ]);

        return back()->with('success', 'Department created successfully.');
    }

    public function updateDepartment(DepartmentRequest $request): RedirectResponse
    {
        $department = (string) $request->route('department');
        $record = Department::query()->where('tenant_id', $this->tenantId())->findOrFail($department);
        $record->update($request->validated());

        return back()->with('success', 'Department updated successfully.');
    }

    public function destroyDepartment(): RedirectResponse
    {
        $department = (string) request()->route('department');
        $tenantId = $this->tenantId();
        $record = Department::query()->where('tenant_id', $tenantId)->findOrFail($department);

        abort_if(
            Position::query()->where('tenant_id', $tenantId)->where('department_id', $record->id)->withTrashed()->exists()
            || DB::table('employees')->where('tenant_id', $tenantId)->where('department_id', $record->id)->exists(),
            409,
            'Reassign this department positions and employees before archiving the department.'
        );

        $record->delete();

        return back()->with('success', 'Department archived successfully.');
    }

    public function storePosition(PositionRequest $request): RedirectResponse
    {
        Position::query()->forceCreate([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
        ]);

        return back()->with('success', 'Position created successfully.');
    }

    public function updatePosition(PositionRequest $request): RedirectResponse
    {
        $position = (string) $request->route('position');
        $record = Position::query()->where('tenant_id', $this->tenantId())->findOrFail($position);
        $record->update($request->validated());

        return back()->with('success', 'Position updated successfully.');
    }

    public function destroyPosition(): RedirectResponse
    {
        $position = (string) request()->route('position');
        $tenantId = $this->tenantId();
        $record = Position::query()->where('tenant_id', $tenantId)->findOrFail($position);

        abort_if(
            DB::table('employees')->where('tenant_id', $tenantId)->where('position_id', $record->id)->exists(),
            409,
            'Reassign employees from this position before archiving it.'
        );

        $record->delete();

        return back()->with('success', 'Position archived successfully.');
    }
}
