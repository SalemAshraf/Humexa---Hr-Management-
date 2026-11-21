<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);
        $manageAll = $user->can('leave.manage');
        $scopeIds = $this->visibleEmployeeIds($tenantId, $user);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
        ]);

        $query = LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->with(['employee:id,tenant_id,employee_number,first_name,middle_name,last_name,manager_id', 'type:id,tenant_id,name'])
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('employee_id', $scopeIds))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id');

        $myEmployeeId = $this->ownEmployeeId($tenantId, $user);
        $year = (int) now()->year;

        $requests = $query->paginate(15)->withQueryString()->through(fn (LeaveRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee?->full_name,
            'employee_number' => $r->employee?->employee_number,
            'type' => $r->type?->name,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'days' => $r->days,
            'reason' => $r->reason,
            'status' => $r->status,
            'review_note' => $r->review_note,
            'can_review' => $r->status === LeaveRequest::PENDING && $this->mayReview($tenantId, $user, $r),
            'can_edit' => $manageAll && $r->status === LeaveRequest::PENDING,
            'leave_type_id' => $r->leave_type_id,
            'can_cancel' => $manageAll && in_array($r->status, [LeaveRequest::PENDING, LeaveRequest::APPROVED], true),
        ]);

        $pendingQuery = LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::PENDING)
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('employee_id', $scopeIds));

        $onLeaveToday = LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::APPROVED)
            ->whereDate('start_date', '<=', now()->toDateString())->whereDate('end_date', '>=', now()->toDateString())
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('employee_id', $scopeIds))->count();

        return Inertia::render('Company/Leave/Index', [
            'requests' => $requests,
            'filters' => ['status' => $filters['status'] ?? null],
            'summary' => [
                'pending' => (clone $pendingQuery)->count(),
                'on_leave_today' => $onLeaveToday,
                'scope' => $manageAll ? 'company' : 'direct_reports',
            ],
            'types' => LeaveType::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'annual_days', 'is_paid', 'is_active']),
            'employees' => $manageAll
                ? Employee::query()->where('tenant_id', $tenantId)
                    ->whereNotIn('employment_status', [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])
                    ->orderBy('first_name')->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name, 'employee_number' => $e->employee_number])
                : [],
            'balances' => $request->integer('employee_id') && $manageAll
                ? $this->leave->balances($tenantId, $request->integer('employee_id'), $year)
                : [],
            'abilities' => [
                'manage' => $manageAll,
                'approve' => $user->can('leave.approve'),
                'own_employee_id' => $myEmployeeId,
            ],
            'settings' => [
                'weekend_days' => $this->leave->weekendDays($tenantId),
                'is_default' => ! LeaveSetting::query()->where('tenant_id', $tenantId)->exists(),
            ],
            'year' => $year,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->user($request);

        $data = $request->validate([
            'weekend_days' => ['present', 'array', 'max:6'],
            'weekend_days.*' => ['integer', 'between:0,6', 'distinct'],
        ]);

        LeaveSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            ['weekend_days' => array_values(array_map('intval', $data['weekend_days']))],
        );

        return back()->with('success', 'Weekend days saved. They apply to new requests only.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->user($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->where('tenant_id', $tenantId)],
            'annual_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_paid' => ['required', 'boolean'],
        ]);

        LeaveType::create($data + ['tenant_id' => $tenantId, 'is_active' => true]);

        return back()->with('success', 'Leave type added.');
    }

    public function updateType(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->user($request);
        $model = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('type'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->where('tenant_id', $tenantId)->ignore($model->id)],
            'annual_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'is_paid' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $model->update($data);

        return back()->with('success', 'Leave type updated.');
    }

    public function destroyType(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->user($request);
        $model = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('type'));

        if (LeaveRequest::query()->where('tenant_id', $tenantId)->where('leave_type_id', $model->id)->exists()) {
            return back()->withErrors(['type' => 'This leave type has requests, so it cannot be deleted. Deactivate it instead.']);
        }

        $model->delete();

        return back()->with('success', 'Leave type deleted.');
    }

    /** Change a pending request. It is re-checked like a new one, and the old row is kept as cancelled history. */
    public function updateRequest(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);

        $old = LeaveRequest::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('leaveRequest'));
        abort_unless($old->status === LeaveRequest::PENDING, 422, 'Only a pending request can be changed. Cancel an approved one and submit a new request.');

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail($data['leave_type_id']);
        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($old->employee_id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($old, $employee, $type, $data, $tenantId, $user) {
            $old->forceFill(['status' => LeaveRequest::CANCELLED, 'review_note' => 'Replaced by an edited request.'])->save();
            $this->leave->submit($tenantId, $employee, $type, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']), $data['reason'] ?? null, $user);
        });

        return back()->with('success', 'Leave request changed.');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Both lookups are scoped to this company, so another company's ids simply do not exist here.
        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($data['employee_id']);
        $type = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail($data['leave_type_id']);

        $this->leave->submit(
            $tenantId, $employee, $type,
            Carbon::parse($data['start_date']), Carbon::parse($data['end_date']),
            $data['reason'] ?? null, $user,
        );

        return back()->with('success', 'Leave request recorded.');
    }

    public function review(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);

        $model = LeaveRequest::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('leaveRequest'));
        abort_unless($this->mayReview($tenantId, $user, $model), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->leave->review($model, $user, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'approve' ? 'Leave approved.' : 'Leave rejected.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->user($request);

        $model = LeaveRequest::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('leaveRequest'));
        abort_unless(in_array($model->status, [LeaveRequest::PENDING, LeaveRequest::APPROVED], true), 422);

        $model->forceFill(['status' => LeaveRequest::CANCELLED])->save();

        return back()->with('success', 'Leave request cancelled.');
    }

    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    private function user(Request $request): User
    {
        $user = $request->user('tenant');
        abort_unless($user instanceof User && $user->tenant_id !== null && hash_equals($this->tenantId(), (string) $user->tenant_id), 403);

        return $user;
    }

    private function ownEmployeeId(string $tenantId, User $user): ?int
    {
        return Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
    }

    /** Null means every employee in the company; otherwise the ids this user may see. */
    private function visibleEmployeeIds(string $tenantId, User $user): ?array
    {
        if ($user->can('leave.manage')) {
            return null;
        }

        $own = $this->ownEmployeeId($tenantId, $user);

        if ($own === null) {
            return [];
        }

        return Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();
    }

    private function mayReview(string $tenantId, User $user, LeaveRequest $request): bool
    {
        if (! $user->can('leave.approve')) {
            return false;
        }

        $own = $this->ownEmployeeId($tenantId, $user);

        // Nobody approves their own leave.
        if ($own !== null && $own === (int) $request->employee_id) {
            return false;
        }

        if ($user->can('leave.manage')) {
            return true;
        }

        return $own !== null && Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($request->employee_id)
            ->where('manager_id', $own)
            ->exists();
    }
}
