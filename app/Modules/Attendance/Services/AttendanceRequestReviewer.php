<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceCorrection;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Models\OvertimeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Applies HR decisions on attendance correction and overtime requests. */
class AttendanceRequestReviewer
{
    public function __construct(private readonly AttendanceMobileAction $summary)
    {
    }

    public function reviewCorrection(AttendanceCorrection $correction, User $reviewer, bool $approve, ?string $note): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $reviewer, $approve, $note) {
            $locked = AttendanceCorrection::query()
                ->where('tenant_id', $correction->tenant_id)
                ->whereKey($correction->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked->status);

            if ($approve) {
                $session = AttendanceSession::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('employee_id', $locked->employee_id)
                    ->whereKey($locked->attendance_session_id)
                    ->lockForUpdate()->firstOrFail();

                $in = $locked->requested_check_in ?? $session->check_in_at;
                $out = $locked->requested_check_out ?? $session->check_out_at;

                if ($in === null) {
                    throw ValidationException::withMessages(['status' => 'The session has no check-in time to correct.']);
                }
                if ($out !== null && $out->lt($in)) {
                    throw ValidationException::withMessages(['status' => 'The corrected check-out is before the check-in.']);
                }
                $record = $session->attendanceRecord()->firstOrFail();
                if ($in->toDateString() !== $record->attendance_date->toDateString()) {
                    throw ValidationException::withMessages(['status' => 'A correction cannot move the check-in to another day.']);
                }

                $session->check_in_at = $in;
                $session->check_out_at = $out;
                if ($out !== null) {
                    $session->worked_minutes = max(0, $in->diffInMinutes($out) - (int) $session->break_minutes);
                    $session->status = 'completed';
                }
                $session->save();
                $this->summary->refreshDailySummary($record);
            }

            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $locked;
        });
    }

    public function reviewOvertime(OvertimeRequest $request, User $reviewer, bool $approve, ?string $note): OvertimeRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $approve, $note) {
            $locked = OvertimeRequest::query()
                ->where('tenant_id', $request->tenant_id)
                ->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked->status);

            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            if ($approve) {
                $session = AttendanceSession::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('employee_id', $locked->employee_id)
                    ->whereKey($locked->attendance_session_id)
                    ->lockForUpdate()->firstOrFail();

                $session->overtime_minutes = (int) OvertimeRequest::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('attendance_session_id', $session->id)
                    ->where('status', 'approved')
                    ->sum('requested_minutes');
                $session->save();
                $this->summary->refreshDailySummary($session->attendanceRecord()->firstOrFail());
            }

            return $locked;
        });
    }

    private function assertPending(string $status): void
    {
        if ($status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request was already reviewed.']);
        }
    }
}
