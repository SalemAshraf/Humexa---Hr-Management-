<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\SaveEmployeeAddressAction;
use App\Modules\Employees\Http\Requests\EmployeeAddressRequest;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class EmployeeAddressController extends Controller
{
    private function employee(string $employeeId): Employee
    {
        abort_unless(tenancy()->initialized, 404);

        return Employee::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->findOrFail($employeeId);
    }

    public function index(string $employee): JsonResponse
    {
        $record = $this->employee($employee);
        $this->authorize('view', $record);

        return response()->json([
            'data' => $record->addresses()->get(),
        ]);
    }

    public function store(
        EmployeeAddressRequest $request,
        string $employee,
        SaveEmployeeAddressAction $action
    ): RedirectResponse {
        $record = $this->employee($employee);
        $this->authorize('update', $record);

        $action->execute($record, $request->validated());

        return back()->with('success', 'Employee address created successfully.');
    }

    public function update(
        EmployeeAddressRequest $request,
        string $employee,
        string $address,
        SaveEmployeeAddressAction $action
    ): RedirectResponse {
        $record = $this->employee($employee);
        $this->authorize('update', $record);

        $addressRecord = $record->addresses()->whereKey($address)->firstOrFail();
        $action->execute($record, $request->validated(), $addressRecord);

        return back()->with('success', 'Employee address updated successfully.');
    }

    public function destroy(string $employee, string $address): RedirectResponse
    {
        $record = $this->employee($employee);
        $this->authorize('update', $record);

        $addressRecord = $record->addresses()->whereKey($address)->firstOrFail();
        $addressRecord->delete();

        return back()->with('success', 'Employee address deleted successfully.');
    }
}
