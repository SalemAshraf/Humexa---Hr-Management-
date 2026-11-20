<?php

declare(strict_types=1);

namespace App\\Modules\\Employees\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use App\\Modules\\Employees\\Actions\\SaveEmployeeDocumentTypeAction;
use App\\Modules\\Employees\\Http\\Requests\\EmployeeDocumentTypeRequest;
use App\\Modules\\Employees\\Models\\EmployeeDocumentType;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\RedirectResponse;

class EmployeeDocumentTypeController extends Controller
{
    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    public function index(string $tenant): JsonResponse
    {
        $types = EmployeeDocumentType::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'is_active', 'created_at']);

        return response()->json(['data' => $types]);
    }

    public function store(
        EmployeeDocumentTypeRequest $request,
        string $tenant,
        SaveEmployeeDocumentTypeAction $action
    ): RedirectResponse {
        $action->execute($this->tenantId(), $request->validated());

        return back()->with('success', 'Document type created successfully.');
    }

    public function update(
        EmployeeDocumentTypeRequest $request,
        string $tenant,
        string $type,
        SaveEmployeeDocumentTypeAction $action
    ): RedirectResponse {
        $record = EmployeeDocumentType::query()
            ->where('tenant_id', $this->tenantId())
            ->findOrFail((string) request()->route('type'));

        $action->execute($this->tenantId(), $request->validated(), $record);

        return back()->with('success', 'Document type updated successfully.');
    }

    public function destroy(string $tenant, string $type): RedirectResponse
    {
        $record = EmployeeDocumentType::query()
            ->where('tenant_id', $this->tenantId())
            ->findOrFail((string) request()->route('type'));

        $record->delete();

        return back()->with('success', 'Document type archived successfully.');
    }
}
