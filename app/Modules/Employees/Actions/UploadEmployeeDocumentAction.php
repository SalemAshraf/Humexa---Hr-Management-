<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeDocument;
use App\Modules\Employees\Models\EmployeeDocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadEmployeeDocumentAction
{
    public function execute(
        Employee $employee,
        array $data,
        UploadedFile $file,
        int|string $uploadedByUserId
    ): EmployeeDocument {
        $disk = (string) config('employee.document_disk', 'local');

        if ($disk === '' || $disk === 'public'
            || config('filesystems.disks.' . $disk . '.visibility') === 'public') {
            throw new RuntimeException('Employee documents must use a private storage disk.');
        }

        $extension = strtolower((string) $file->guessExtension());

        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new RuntimeException('The uploaded document has an unsupported file type.');
        }

        $tenantId = (string) $employee->tenant_id;
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $employee,
                $data,
                $file,
                $uploadedByUserId,
                $disk,
                $extension,
                $tenantId,
                &$storedPath
            ) {
                $lockedEmployee = Employee::query()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($employee->id);

                EmployeeDocumentType::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->whereKey($data['document_type_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $directory = 'tenants/' . $tenantId . '/employees/' . $lockedEmployee->id . '/documents';
                $storedName = (string) Str::uuid() . '.' . $extension;
                $storedPath = $file->storeAs($directory, $storedName, $disk);

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('The document could not be stored.');
                }

                $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                $originalName = mb_strcut($originalName, 0, 255, 'UTF-8');

                return EmployeeDocument::query()->forceCreate([
                    'tenant_id' => $tenantId,
                    'employee_id' => $lockedEmployee->id,
                    'document_type_id' => $data['document_type_id'],
                    'title' => $data['title'],
                    'original_file_name' => $originalName,
                    'file_path' => $storedPath,
                    'disk' => $disk,
                    'mime_type' => (string) $file->getMimeType(),
                    'file_size' => (int) $file->getSize(),
                    'issue_date' => $data['issue_date'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'uploaded_by_user_id' => $uploadedByUserId,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk($disk)->delete($storedPath);
            }

            throw $exception;
        }
    }
}
