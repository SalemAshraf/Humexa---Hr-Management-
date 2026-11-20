<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\UploadEmployeeDocumentAction;
use App\Modules\Employees\Http\Requests\EmployeeDocumentRequest;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    private function employee(): Employee
    {
        abort_unless(tenancy()->initialized, 404);

        return Employee::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->findOrFail((string) request()->route('employee'));
    }

    private function document(Employee $employee): EmployeeDocument
    {
        return $employee->documents()
            ->withTrashed()
            ->whereKey((string) request()->route('document'))
            ->firstOrFail();
    }

    public function index(string $tenant, string $employee): JsonResponse
    {
        $record = $this->employee();
        $this->authorize('view', $record);

        $documents = $record->documents()
            ->withTrashed()
            ->with('documentType')
            ->get()
            ->map(fn ($document) => [
                'id' => $document->id,
                'document_type_id' => $document->document_type_id,
                'document_type_name' => $document->documentType?->name,
                'title' => $document->title,
                'original_file_name' => $document->original_file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'issue_date' => $document->issue_date?->toDateString(),
                'expiry_date' => $document->expiry_date?->toDateString(),
                'is_expired' => $document->is_expired,
                'notes' => $document->notes,
                'is_archived' => $document->trashed(),
                'created_at' => $document->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $documents]);
    }

    public function store(
        EmployeeDocumentRequest $request,
        string $tenant,
        string $employee,
        UploadEmployeeDocumentAction $action
    ): RedirectResponse {
        $record = $this->employee();
        $this->authorize('update', $record);

        $action->execute(
            $record,
            $request->validated(),
            $request->file('file'),
            $request->user('tenant')->getAuthIdentifier()
        );

        return back()->with('success', 'Employee document uploaded successfully.');
    }

    public function download(string $tenant, string $employee, string $document): StreamedResponse
    {
        $record = $this->employee();
        $this->authorize('view', $record);

        $documentRecord = $this->document($record);
        $disk = Storage::disk($documentRecord->disk);

        abort_unless($disk->exists($documentRecord->file_path), 404);

        return $disk->download(
            $documentRecord->file_path,
            $documentRecord->original_file_name,
            [
                'Content-Type' => $documentRecord->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function preview(string $tenant, string $employee, string $document): StreamedResponse
    {
        $record = $this->employee();
        $this->authorize('view', $record);

        $documentRecord = $this->document($record);
        abort_unless(in_array($documentRecord->mime_type, [
            'application/pdf', 'image/jpeg', 'image/png',
        ], true), 415);

        $disk = Storage::disk($documentRecord->disk);
        abort_unless($disk->exists($documentRecord->file_path), 404);

        return $disk->response(
            $documentRecord->file_path,
            $documentRecord->original_file_name,
            [
                'Content-Type' => $documentRecord->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
                'Cache-Control' => 'private, no-store',
            ],
            'inline'
        );
    }

    public function destroy(string $tenant, string $employee, string $document): RedirectResponse
    {
        $record = $this->employee();
        $this->authorize('update', $record);

        $this->document($record)->delete();

        return back()->with('success', 'Employee document archived successfully.');
    }
}
