<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Services\LeaveService;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request): Response
    {
        return Inertia::render('Company/Reports/Index', $this->build($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->build($request);
        $rows = [['section', 'label', 'value'], ['Report', 'Period from', $data['from']], ['Report', 'Period to', $data['to']], ['Report', 'Scope', $data['scope'] === 'company' ? 'Whole company' : 'Direct reports only']];

        foreach ($data['sections'] as $name => $items) {
            foreach ($items as $item) {
                $rows[] = [$name, $item['label'], $item['value']];
            }
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, sprintf('humexa-report-%s-to-%s.csv', $data['from'], $data['to']), ['Content-Type' => 'text/csv']);
    }

    private function build(Request $request): array
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);
        $to = Carbon::parse($request->query('to', now()->toDateString()))->startOfDay();
        $from = Carbon::parse($request->query('from', $to->copy()->subDays(29)->toDateString()))->startOfDay();
        abort_if($from->gt($to) || $from->diffInDays($to) > 366, 422, 'Choose a range of at most one year.');

        $scope = $this->scopeIds($tenantId, $user);
        $inScope = fn ($q, string $col = 'employee_id') => $scope === null ? $q : $q->whereIn($col, $scope);

        $employees = $inScope(Employee::query()->where('tenant_id', $tenantId), 'id');
        $byStatus = (clone $employees)->selectRaw('employment_status as k, count(*) as n')->groupBy('employment_status')->pluck('n', 'k');
        $deptNames = Department::query()->pluck('name', 'id');
        $byDept = (clone $employees)->whereNotIn('employment_status', ['resigned', 'terminated', 'draft'])
            ->selectRaw('department_id as k, count(*) as n')->groupBy('department_id')->pluck('n', 'k');

        $att = $inScope(AttendanceRecord::query()->where('tenant_id', $tenantId)->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]));
        $attRow = (clone $att)->selectRaw('count(*) as days, coalesce(sum(late_minutes),0) as late, coalesce(sum(overtime_minutes),0) as ot, coalesce(sum(total_worked_minutes),0) as worked, coalesce(sum(case when late_minutes > 0 then 1 else 0 end),0) as late_days')->first();

        $leave = $inScope(LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::APPROVED)
            ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString()));
        $weekend = app(LeaveService::class)->weekendDays($tenantId);
        $leaveByType = (clone $leave)->with('type:id,name')->get()->groupBy(fn ($r) => $r->type?->name ?? 'Unknown')->map(fn ($g) => round((float) $g->sum(
            fn ($r) => app(LeaveService::class)->workingDays($r->start_date->copy()->max($from), $r->end_date->copy()->min($to), $weekend)
        ), 1));
        $pendingLeave = $inScope(LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::PENDING))->count();

        $sections = [
            'Headcount' => collect($byStatus)->map(fn ($n, $k) => ['label' => ucfirst(str_replace('_', ' ', (string) $k)), 'value' => (int) $n])->values()->all(),
            'Current headcount by department' => collect($byDept)->map(fn ($n, $k) => ['label' => $deptNames[$k] ?? 'No department', 'value' => (int) $n])->values()->all(),
            'Attendance' => [
                ['label' => 'Days recorded', 'value' => (int) $attRow->days],
                ['label' => 'Days with late arrival', 'value' => (int) $attRow->late_days],
                ['label' => 'Total late minutes', 'value' => (int) $attRow->late],
                ['label' => 'Total overtime hours', 'value' => round($attRow->ot / 60, 1)],
                ['label' => 'Average hours per recorded day', 'value' => $attRow->days ? round($attRow->worked / 60 / $attRow->days, 1) : 0],
            ],
            'Approved leave, working days inside the period' => $leaveByType->map(fn ($d, $k) => ['label' => $k, 'value' => $d])->values()->all()
                + [],
        ];
        $sections['Approved leave, working days inside the period'][] = ['label' => 'Requests waiting for a decision', 'value' => $pendingLeave];

        if ($user->can('recruitment.view')) {
            $stages = Candidate::query()->where('tenant_id', $tenantId)->selectRaw('stage as k, count(*) as n')->groupBy('stage')->pluck('n', 'k');
            $sections['Recruitment pipeline'] = collect(Candidate::STAGES)->map(fn ($s) => ['label' => ucfirst($s), 'value' => (int) ($stages[$s] ?? 0)])->all();
            $sections['Recruitment pipeline'][] = ['label' => 'Open job openings', 'value' => JobOpening::query()->where('tenant_id', $tenantId)->where('status', 'open')->count()];
        }

        if ($user->can('payroll.view')) {
            $runs = PayrollRun::query()->where('tenant_id', $tenantId)->orderByDesc('year')->orderByDesc('month')->limit(6)->get();
            $sections['Payroll runs, net total (EGP)'] = $runs->map(fn ($r) => [
                'label' => sprintf('%04d-%02d (%s)', $r->year, $r->month, $r->status === 'approved' ? 'locked' : 'draft'),
                'value' => round((float) Payslip::where('payroll_run_id', $r->id)->sum('net_pay'), 2),
            ])->all();
        }

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'scope' => $scope === null ? 'company' : 'direct_reports',
            'sections' => $sections,
            'no_bars' => ['Attendance', 'Approved leave, working days inside the period'],
            'notes' => ['Current headcount by department' => 'Everyone who is not resigned, terminated or still a draft. Active, on leave, probation and suspended are all counted.'],
        ];
    }

    /** null = whole company (HR), otherwise the user's direct reports. */
    private function scopeIds(string $tenantId, User $user): ?array
    {
        if ($user->can('leave.manage') || $user->can('employees.manage')) {
            return null;
        }

        $own = Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');

        return $own === null ? [] : Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();
    }
}
