<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceCorrection;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeDocument;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Performance\Models\PerformanceReview;
use App\Modules\Recruitment\Models\Candidate;

/**
 * "Needs your attention" items, computed live from current records and limited to
 * what the signed-in account may open. Nothing is stored, so nothing can go stale.
 */
class AttentionService
{
    public const DOCUMENT_WINDOW_DAYS = 30;

    /** @return list<array{key:string,group:string,title:string,detail:string,count:int,url:string,tone:string}> */
    public function items(User $user, string $tenantId): array
    {
        $items = [];
        $push = function (string $key, string $group, string $title, string $detail, int $count, string $url, string $tone = 'amber') use (&$items): void {
            if ($count > 0) {
                $items[] = compact('key', 'group', 'title', 'detail', 'count', 'url', 'tone');
            }
        };

        if ($user->can('leave.approve')) {
            $own = Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
            $q = LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::PENDING)
                ->when($own !== null, fn ($x) => $x->where('employee_id', '!=', $own));

            if (! $user->can('leave.manage')) {
                $reports = $own === null ? [] : Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();
                $q->whereIn('employee_id', $reports);
            }

            $push('leave', 'Leave', 'Leave requests waiting for your decision', 'Approve or reject them so employees can plan.', $q->count(), '/leave?status=pending');
        }

        if ($user->can('attendance.manage')) {
            $push('corrections', 'Attendance', 'Attendance corrections to review', 'Employees asked to fix their recorded times.',
                AttendanceCorrection::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(), '/attendance/requests');
            $push('overtime', 'Attendance', 'Overtime requests to review', 'Approved overtime is added to the day total.',
                OvertimeRequest::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count(), '/attendance/requests');
        }

        if ($user->can('performance.manage')) {
            $push('reviews', 'Performance', 'Performance reviews waiting for HR', 'Managers submitted them. Finalize to lock them.',
                PerformanceReview::query()->where('tenant_id', $tenantId)->where('status', PerformanceReview::SUBMITTED)->count(), '/performance');
        }

        if ($user->can('recruitment.view')) {
            $push('offers', 'Recruitment', 'Candidates at the offer stage', 'Hire them or move them on.',
                Candidate::query()->where('tenant_id', $tenantId)->where('stage', 'offer')->count(), '/recruitment?stage=offer', 'cyan');
        }

        if ($user->can('employees.manage')) {
            $push('drafts', 'Employees', 'Employee records still in draft', 'Complete them to make the employees active.',
                Employee::query()->where('tenant_id', $tenantId)->where('employment_status', Employee::STATUS_DRAFT)->count(), '/employees', 'cyan');

            $expired = EmployeeDocument::query()->where('tenant_id', $tenantId)->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
            $soon = EmployeeDocument::query()->where('tenant_id', $tenantId)->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays(self::DOCUMENT_WINDOW_DAYS));
            $push('docs_expired', 'Documents', 'Employee documents that have expired', 'Ask for renewed copies.', $expired->count(), '/documents/expiry', 'red');
            $push('docs_soon', 'Documents', 'Employee documents expiring within '.self::DOCUMENT_WINDOW_DAYS.' days', 'Plan renewals before they lapse.', $soon->count(), '/documents/expiry');
        }

        return $items;
    }

    public function total(User $user, string $tenantId): int
    {
        return array_sum(array_column($this->items($user, $tenantId), 'count'));
    }
}
