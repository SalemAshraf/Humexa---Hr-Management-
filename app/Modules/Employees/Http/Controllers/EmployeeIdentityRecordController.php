<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\SaveEmployeeIdentityRecordAction;
use App\Modules\Employees\Http\Requests\EmployeeIdentityRecordRequest;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EmployeeIdentityRecordController extends Controller
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

        $records = $record->identityRecords()
            ->get()
            ->map(fn ($identity) => [
                'id' => $identity->id,
                'identity_type' => $identity->identity_type,
                'masked_number' => $identity->masked_number,
                'issuing_country' => $identity->issuing_country,
                'issue_date' => $identity->issue_date?->toDateString(),
                'expiry_date' => $identity->expiry_date?->toDateString(),
                'is_current' => $identity->is_current,
                'created_at' => $identity->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $records]);
    }

    public function store(
        EmployeeIdentityRecordRequest $request,
        string $tenant,
        string $employee,
        SaveEmployeeIdentityRecordAction $action
    ): RedirectResponse {
        $record = $this->employee();
        $this->authorize('update', $record);

        $action->execute($record, $request->validated());

        return back()->with('success', 'Employee identity record saved successfully.');
    }
}
