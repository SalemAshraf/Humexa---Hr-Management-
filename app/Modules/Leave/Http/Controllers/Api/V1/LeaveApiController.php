<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee self-service leave over the mobile token. It reuses LeaveService::submit, so balances,
 * overlap, weekend and active-type rules are the same as the web self-service page.
 */
class LeaveApiController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    private function employee(Request $request): Employee
    {
        abort_unless(tenancy()->initialized, 404);
        $user = $request->user();
        abort_unless($user && $user->account_type === 'tenant' && (string) $user->tenant_id === (string) tenant('id'), 403);

        return Employee::query()->where('tenant_id', tenant('id'))->where('user_id', $user->id)->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $tenantId = (string) tenant('id');

        return response()->json(['data' => [
            'balances' => $this->leave->balances($tenantId, $employee->id, (int) now()->year),
            'requests' => LeaveRequest::query()
                ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
                ->with('type:id,name')->latest()->limit(20)
                ->get(['id', 'leave_type_id', 'start_date', 'end_date', 'days', 'status', 'reason', 'review_note']),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $tenantId = (string) tenant('id');
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail($data['leave_type_id']);
        $created = $this->leave->submit($tenantId, $employee, $type, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']), $data['reason'] ?? null, $request->user());

        return response()->json(['data' => $created], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $model = LeaveRequest::query()
            ->where('tenant_id', tenant('id'))->where('employee_id', $employee->id)
            ->findOrFail((int) $request->route('leaveRequest'));
        abort_unless($model->status === LeaveRequest::PENDING, 422, 'Only a pending request can be withdrawn.');
        $model->forceFill(['status' => LeaveRequest::CANCELLED, 'review_note' => 'Withdrawn by the employee.'])->save();

        return response()->json(['data' => $model]);
    }
}
