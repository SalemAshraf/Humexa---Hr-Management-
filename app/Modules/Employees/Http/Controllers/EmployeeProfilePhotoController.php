<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\SaveEmployeeProfilePhotoAction;
use App\Modules\Employees\Http\Requests\EmployeeProfilePhotoRequest;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeProfilePhotoController extends Controller
{
    private function employee(): Employee
    {
        abort_unless(tenancy()->initialized, 404);

        return Employee::query()
            ->where('tenant_id', tenant()->getTenantKey())
            ->findOrFail((string) request()->route('employee'));
    }

    public function show(string $tenant, string $employee): StreamedResponse
    {
        $record = $this->employee();
        $this->authorize('view', $record);

        $path = $record->profile_photo_path;
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = (string) config('employee.profile_photo_disk', 'local');
        abort_if($disk === '' || $disk === 'public'
            || config('filesystems.disks.' . $disk . '.visibility') === 'public', 404);

        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        $mime = (string) $storage->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        // Read the file while the tenant filesystem context is still active.
        // A deferred StreamedResponse may execute after tenancy middleware has
        // restored the central filesystem root, resulting in an empty body.
        $contents = $storage->get($path);
        abort_unless(is_string($contents) && $contents !== '', 404);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(
        EmployeeProfilePhotoRequest $request,
        string $tenant,
        string $employee,
        SaveEmployeeProfilePhotoAction $action
    ): RedirectResponse {
        $record = $this->employee();
        $this->authorize('update', $record);

        $action->execute($record, $request->file('photo'));

        return back()->with('success', 'Employee profile photo updated successfully.');
    }

    public function destroy(string $tenant, string $employee): RedirectResponse
    {
        $record = $this->employee();
        $this->authorize('update', $record);

        $tenantId = (string) tenant()->getTenantKey();
        $directory = 'tenants/' . $tenantId . '/employees/' . $record->id . '/profile';

        [$path] = DB::transaction(function () use ($record, $tenantId): array {
            $lockedEmployee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($record->id);

            $oldPath = $lockedEmployee->profile_photo_path;
            $lockedEmployee->forceFill(['profile_photo_path' => null])->save();

            return [$oldPath];
        });

        $disk = (string) config('employee.profile_photo_disk', 'local');

        if (is_string($path) && str_starts_with($path, $directory . '/')
            && $disk !== '' && $disk !== 'public'
            && config('filesystems.disks.' . $disk . '.visibility') !== 'public') {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Unable to delete removed employee profile photo.', [
                    'tenant_id' => $tenantId,
                    'employee_id' => $record->id,
                ]);
            }
        }

        return back()->with('success', 'Employee profile photo removed successfully.');
    }
}
