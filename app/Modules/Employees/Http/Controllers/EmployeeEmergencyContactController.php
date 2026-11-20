<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\SaveEmployeeEmergencyContactAction;
use App\Modules\Employees\Http\Requests\EmployeeEmergencyContactRequest;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EmployeeEmergencyContactController extends Controller
{
    private function employee(): Employee
    {
        abort_unless(tenancy()->initialized, 404);

        return Employee::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->findOrFail((string) request()->route('employee'));
    }

    public function index(string $tenant, string $employee): JsonResponse
    {
        $record = $this->employee();
        $this->authorize('view', $record);

        return response()->json([
            'data' => $record->emergencyContacts()->get(),
        ]);
    }

    public function store(
        EmployeeEmergencyContactRequest $request,
        string $tenant,
        string $employee,
        SaveEmployeeEmergencyContactAction $action
    ): RedirectResponse {
        $record = $this->employee();
        $this->authorize('update', $record);

        $action->execute($record, $request->validated());

        return back()->with('success', 'Emergency contact created successfully.');
    }

    public function update(
        EmployeeEmergencyContactRequest $request,
        string $tenant,
        string $employee,
        string $contact,
        SaveEmployeeEmergencyContactAction $action
    ): RedirectResponse {
        $record = $this->employee();
        $this->authorize('update', $record);

        $contactRecord = $record->emergencyContacts()
            ->whereKey((string) request()->route('contact'))
            ->firstOrFail();

        $action->execute($record, $request->validated(), $contactRecord);

        return back()->with('success', 'Emergency contact updated successfully.');
    }

    public function destroy(string $tenant, string $employee, string $contact): RedirectResponse
    {
        $record = $this->employee();
        $this->authorize('update', $record);

        $contactRecord = $record->emergencyContacts()
            ->whereKey((string) request()->route('contact'))
            ->firstOrFail();

        $contactRecord->delete();

        return back()->with('success', 'Emergency contact deleted successfully.');
    }
}
