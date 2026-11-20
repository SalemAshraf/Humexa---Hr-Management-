<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\SaveEmployeeProfilePhotoAction;
use App\Modules\Employees\Http\Requests\EmployeeProfilePhotoRequest;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return $storage->response($path, null, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
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

        $path = $record->profile_photo_path;

        if (is_string($path) && $path !== '') {
            $directory = 'tenants/' . tenant()->getTenantKey() . '/employees/' . $record->id . '/profile';

            $record->forceFill(['profile_photo_path' => null])->save();

            if (str_starts_with($path, $directory . '/')) {
                Storage::disk((string) config('employee.profile_photo_disk', 'local'))->delete($path);
            }
        }

        return back()->with('success', 'Employee profile photo removed successfully.');
    }
}
