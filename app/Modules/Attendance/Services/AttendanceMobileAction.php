<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceEvent;
use App\Modules\Attendance\Models\AttendanceLocation;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceMobileAction
{
    public function record(Employee $employee, string $type, array $data, ?UploadedFile $selfie, string $source = 'mobile'): AttendanceSession
    {
        $tenantId = (string) tenant('id');
        $policy = AttendancePolicy::query()->where('tenant_id', $tenantId)->firstOrFail();

        if (! $policy->mobile_checkin_enabled) {
            throw ValidationException::withMessages(['attendance' => 'Mobile attendance is disabled by company policy.']);
        }

        if ($policy->location_verification_enabled && (! isset($data['latitude'], $data['longitude'], $data['accuracy']))) {
            throw ValidationException::withMessages(['location' => 'Location coordinates and accuracy are required.']);
        }

        if ($policy->selfie_verification_enabled && ! $selfie) {
            throw ValidationException::withMessages(['selfie' => 'A selfie is required by company policy.']);
        }

        $location = null;

        if ($policy->location_verification_enabled) {
            if ((int) $data['accuracy'] > $policy->location_accuracy_limit) {
                throw ValidationException::withMessages(['accuracy' => 'GPS accuracy is outside the company limit.']);
            }

            $locations = AttendanceLocation::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();

            $location = $locations->first(fn ($loc) => $this->distanceMeters(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $loc->latitude,
                (float) $loc->longitude,
            ) <= $loc->radius_meters);

            if (! $location) {
                throw ValidationException::withMessages(['location' => 'You are outside an approved attendance location.']);
            }
        }

        $storedSelfie = $selfie
            ? $selfie->store('tenants/'.$tenantId.'/attendance/selfies', 'local')
            : null;

        try {
            return DB::transaction(function () use ($employee, $tenantId, $type, $data, $storedSelfie, $location, $source) {
                $employee = Employee::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($employee->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $now = now();
                $date = $now->toDateString();

                // Lock/search the employee's open session across dates so overnight work
                // cannot accidentally create overlapping sessions or lose its check-out.
                $openSession = AttendanceSession::query()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->whereNotNull('check_in_at')
                    ->whereNull('check_out_at')
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($type === 'check_in') {
                    if ($openSession) {
                        throw ValidationException::withMessages(['attendance' => 'An attendance session is already open. Check out before starting another session.']);
                    }

                    $dailyRecord = AttendanceRecord::query()
                        ->where('tenant_id', $tenantId)
                        ->where('employee_id', $employee->id)
                        ->whereDate('attendance_date', $date)
                        ->lockForUpdate()
                        ->first();

                    if (! $dailyRecord) {
                        $dailyRecord = AttendanceRecord::create([
                            'tenant_id' => $tenantId,
                            'employee_id' => $employee->id,
                            'attendance_date' => $date,
                            'status' => 'recorded',
                        ]);
                    }

                    $nextSessionNumber = ((int) AttendanceSession::query()
                        ->where('tenant_id', $tenantId)
                        ->where('attendance_record_id', $dailyRecord->id)
                        ->max('session_number')) + 1;

                    $session = AttendanceSession::create([
                        'tenant_id' => $tenantId,
                        'attendance_record_id' => $dailyRecord->id,
                        'employee_id' => $employee->id,
                        'session_number' => $nextSessionNumber,
                        'check_in_at' => $now,
                        'check_in_source' => $source,
                        'status' => 'open',
                    ]);
                } else {
                    if (! $openSession) {
                        throw ValidationException::withMessages(['attendance' => 'There is no open attendance session to check out from.']);
                    }

                    $dailyRecord = $openSession->attendanceRecord()->firstOrFail();

                    if ($openSession->breaks()->whereNull('ended_at')->exists()) {
                        throw ValidationException::withMessages(['attendance' => 'End the active break before checking out.']);
                    }

                    $breakMinutes = (int) $openSession->breaks()
                        ->whereNotNull('ended_at')
                        ->get()
                        ->sum(fn ($break) => max(0, $break->started_at->diffInMinutes($break->ended_at)));

                    $openSession->check_out_at = $now;
                    $openSession->check_out_source = $source;
                    $openSession->break_minutes = $breakMinutes;
                    $openSession->worked_minutes = max(
                        0,
                        $openSession->check_in_at->diffInMinutes($now) - $breakMinutes
                    );
                    $openSession->status = 'completed';
                    $openSession->save();

                    $session = $openSession;
                }

                AttendanceEvent::create([
                    'tenant_id' => $tenantId,
                    'attendance_session_id' => $session->id,
                    'employee_id' => $employee->id,
                    'event_type' => $type,
                    'source' => $source,
                    'occurred_at' => $now,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'location_accuracy' => $data['accuracy'] ?? null,
                    'location_id' => $location?->id,
                    'media_path' => $storedSelfie,
                    'metadata' => ['client_timestamp' => $data['client_timestamp'] ?? null],
                ]);

                $this->refreshDailySummary($dailyRecord);

                return $session->fresh(['breaks']);
            }, 3);
        } catch (\Throwable $e) {
            if ($storedSelfie) {
                Storage::disk('local')->delete($storedSelfie);
            }

            throw $e;
        }
    }

    public function refreshDailySummary(AttendanceRecord $record): void
    {
        $sessions = AttendanceSession::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('attendance_record_id', $record->id)
            ->get();

        $record->forceFill([
            'total_worked_minutes' => (int) $sessions->sum('worked_minutes'),
            'total_break_minutes' => (int) $sessions->sum('break_minutes'),
            'late_minutes' => (int) $sessions->sum('late_minutes'),
            'early_leave_minutes' => (int) $sessions->sum('early_leave_minutes'),
            'overtime_minutes' => (int) $sessions->sum('overtime_minutes'),
            'status' => $sessions->contains(fn ($session) => $session->check_in_at && ! $session->check_out_at)
                ? 'in_progress'
                : 'recorded',
        ])->save();
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
