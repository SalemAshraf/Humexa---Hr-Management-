<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SaveEmployeeProfilePhotoAction
{
    public function execute(Employee $employee, UploadedFile $photo): Employee
    {
        $disk = (string) config('employee.profile_photo_disk', 'local');

        if ($disk === '' || $disk === 'public'
            || config('filesystems.disks.' . $disk . '.visibility') === 'public') {
            throw new RuntimeException('Employee profile photos must use a private storage disk.');
        }

        $mime = (string) $photo->getMimeType();
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (! isset($extensions[$mime])) {
            throw new RuntimeException('The uploaded profile photo has an unsupported file type.');
        }

        $tenantId = (string) $employee->tenant_id;
        $directory = 'tenants/' . $tenantId . '/employees/' . $employee->id . '/profile';
        $newPath = null;
        $oldPath = null;

        try {
            $updatedEmployee = DB::transaction(function () use (
                $employee,
                $photo,
                $disk,
                $mime,
                $extensions,
                $tenantId,
                $directory,
                &$newPath,
                &$oldPath
            ): Employee {
                $lockedEmployee = Employee::query()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($employee->id);

                $newPath = $photo->storeAs(
                    $directory,
                    (string) Str::uuid() . '.' . $extensions[$mime],
                    $disk
                );

                if (! is_string($newPath) || $newPath === '') {
                    throw new RuntimeException('The profile photo could not be stored.');
                }

                $oldPath = $lockedEmployee->profile_photo_path;
                $lockedEmployee->forceFill(['profile_photo_path' => $newPath])->save();

                return $lockedEmployee;
            });
        } catch (Throwable $exception) {
            if (is_string($newPath) && $newPath !== '') {
                Storage::disk($disk)->delete($newPath);
            }

            throw $exception;
        }

        if (is_string($oldPath) && str_starts_with($oldPath, $directory . '/')) {
            try {
                Storage::disk($disk)->delete($oldPath);
            } catch (Throwable $exception) {
                Log::warning('Unable to delete replaced employee profile photo.', [
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                ]);
            }
        }

        return $updatedEmployee;
    }
}
