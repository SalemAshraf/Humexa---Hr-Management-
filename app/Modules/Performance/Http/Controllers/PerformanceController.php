<?php

declare(strict_types=1);

namespace App\Modules\Performance\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Performance\Models\PerformanceReview;
use App\Modules\Performance\Models\ReviewCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);
        $manageAll = $user->can('performance.manage');
        $scope = $this->scopeIds($tenantId, $user);

        $cycles = ReviewCycle::query()->where('tenant_id', $tenantId)->orderByDesc('starts_on')->orderByDesc('id')->get()
            ->map(fn (ReviewCycle $c) => [
                'id' => $c->id, 'name' => $c->name, 'starts_on' => $c->starts_on->toDateString(), 'ends_on' => $c->ends_on->toDateString(), 'status' => $c->status,
                'review_count' => PerformanceReview::where('tenant_id', $tenantId)->where('review_cycle_id', $c->id)->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope))->count(),
            ]);

        $selected = $cycles->firstWhere('id', $request->integer('cycle')) ?? $cycles->firstWhere('status', 'open') ?? $cycles->first();

        $reviews = $selected === null ? collect() : PerformanceReview::query()
            ->where('tenant_id', $tenantId)->where('review_cycle_id', $selected['id'])
            ->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope))
            ->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')
            ->orderBy('id')->get()
            ->map(fn (PerformanceReview $r) => [
                'id' => $r->id, 'employee' => $r->employee?->full_name, 'employee_number' => $r->employee?->employee_number,
                'rating' => $r->rating, 'strengths' => $r->strengths, 'improvements' => $r->improvements, 'goals' => $r->goals,
                'status' => $r->status,
                'can_edit' => $selected['status'] === 'open' && $r->status !== PerformanceReview::FINALIZED,
            ]);

        $reviewed = $selected === null ? [] : PerformanceReview::where('tenant_id', $tenantId)->where('review_cycle_id', $selected['id'])->pluck('employee_id')->all();

        return Inertia::render('Company/Performance/Index', [
            'cycles' => $cycles->values(),
            'selected' => $selected,
            'reviews' => $reviews->values(),
            'candidates' => $selected && $selected['status'] === 'open'
                ? Employee::query()->where('tenant_id', $tenantId)
                    ->whereNotIn('employment_status', [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED, Employee::STATUS_DRAFT])
                    ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))
                    ->whereNotIn('id', $reviewed)
                    ->orderBy('first_name')->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name, 'employee_number' => $e->employee_number])->values()
                : [],
            'summary' => $this->summary($tenantId, $selected['id'] ?? null, $scope),
            'abilities' => ['manage' => $manageAll],
            'scope' => $manageAll ? 'company' : 'direct_reports',
        ]);
    }

    public function storeCycle(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);

        ReviewCycle::create($this->cycleData($request, $tenantId) + ['tenant_id' => $tenantId]);

        return back()->with('success', 'Review cycle created.');
    }

    public function updateCycle(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);
        $cycle = ReviewCycle::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('cycle'));

        $cycle->update($this->cycleData($request, $tenantId, $cycle->id));

        return back()->with('success', 'Review cycle updated.');
    }

    public function destroyCycle(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);
        $cycle = ReviewCycle::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('cycle'));

        if ($cycle->reviews()->where('tenant_id', $tenantId)->exists()) {
            return back()->withErrors(['cycle' => 'This cycle has reviews. Close it instead of deleting it.']);
        }

        $cycle->delete();

        return redirect('/performance')->with('success', 'Review cycle deleted.');
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);

        $data = $request->validate(['review_cycle_id' => ['required', 'integer'], 'employee_id' => ['required', 'integer']]);
        $cycle = ReviewCycle::query()->where('tenant_id', $tenantId)->findOrFail($data['review_cycle_id']);
        abort_unless($cycle->status === 'open', 422, 'This cycle is closed.');

        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($data['employee_id']);
        abort_unless($this->mayReview($tenantId, $user, (int) $employee->id), 403);

        if (PerformanceReview::where('review_cycle_id', $cycle->id)->where('employee_id', $employee->id)->exists()) {
            return back()->withErrors(['employee_id' => 'This employee already has a review in this cycle.']);
        }

        PerformanceReview::create([
            'tenant_id' => $tenantId, 'review_cycle_id' => $cycle->id, 'employee_id' => $employee->id,
            'reviewer_user_id' => $user->id, 'status' => PerformanceReview::DRAFT,
        ]);

        return back()->with('success', 'Review started.');
    }

    public function updateReview(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);
        $review = $this->review($tenantId, $request);
        abort_unless($this->mayReview($tenantId, $user, (int) $review->employee_id), 403);
        abort_unless(ReviewCycle::where('tenant_id', $tenantId)->whereKey($review->review_cycle_id)->value('status') === 'open', 422, 'This cycle is closed.');
        abort_if($review->status === PerformanceReview::FINALIZED, 422, 'This review is finalized.');

        $data = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:3000'],
            'improvements' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in([PerformanceReview::DRAFT, PerformanceReview::SUBMITTED])],
        ]);

        if ($data['status'] === PerformanceReview::SUBMITTED && empty($data['rating'])) {
            return back()->withErrors(['rating' => 'Add a rating before submitting.']);
        }

        $review->update($data + ['reviewer_user_id' => $user->id]);

        return back()->with('success', $data['status'] === PerformanceReview::SUBMITTED ? 'Review submitted.' : 'Draft saved.');
    }

    public function finalize(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);
        $review = $this->review($tenantId, $request);

        if ($review->status !== PerformanceReview::SUBMITTED) {
            return back()->withErrors(['status' => 'Only a submitted review can be finalized.']);
        }

        $review->update(['status' => PerformanceReview::FINALIZED, 'finalized_at' => now()]);

        return back()->with('success', 'Review finalized.');
    }

    public function reopen(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);
        $review = $this->review($tenantId, $request);

        if ($review->status !== PerformanceReview::SUBMITTED) {
            return back()->withErrors(['status' => 'Only a submitted review can be sent back to draft.']);
        }

        abort_unless(ReviewCycle::where('tenant_id', $tenantId)->whereKey($review->review_cycle_id)->value('status') === 'open', 422, 'This cycle is closed.');

        $review->update(['status' => PerformanceReview::DRAFT]);

        return back()->with('success', 'Review sent back to draft for changes.');
    }

    public function destroyReview(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->requireManage($request);
        $review = $this->review($tenantId, $request);

        if ($review->status !== PerformanceReview::DRAFT) {
            return back()->withErrors(['status' => 'Only a draft review can be deleted. Send a submitted review back to draft first, or keep it as the record.']);
        }

        $review->delete();

        return back()->with('success', 'Review deleted.');
    }

    private function review(string $tenantId, Request $request): PerformanceReview
    {
        return PerformanceReview::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('review'));
    }

    private function requireManage(Request $request): User
    {
        $user = $this->tenantUser($request);
        abort_unless($user->can('performance.manage'), 403);

        return $user;
    }

    private function cycleData(Request $request, string $tenantId, ?int $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('review_cycles', 'name')->where('tenant_id', $tenantId)->ignore($ignore)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
    }

    private function ownEmployeeId(string $tenantId, User $user): ?int
    {
        return Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
    }

    /** Null means every employee; otherwise the direct reports this user may review. */
    private function scopeIds(string $tenantId, User $user): ?array
    {
        if ($user->can('performance.manage')) {
            return null;
        }

        $own = $this->ownEmployeeId($tenantId, $user);

        return $own === null ? [] : Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();
    }

    private function mayReview(string $tenantId, User $user, int $employeeId): bool
    {
        $own = $this->ownEmployeeId($tenantId, $user);

        if ($own !== null && $own === $employeeId) {
            return false; // nobody reviews themselves
        }

        $scope = $this->scopeIds($tenantId, $user);

        return $scope === null || in_array($employeeId, $scope, true);
    }

    private function summary(string $tenantId, ?int $cycleId, ?array $scope): array
    {
        if ($cycleId === null) {
            return ['total' => 0, 'submitted' => 0, 'finalized' => 0, 'average' => null];
        }

        $q = fn () => PerformanceReview::where('tenant_id', $tenantId)->where('review_cycle_id', $cycleId)->when($scope !== null, fn ($x) => $x->whereIn('employee_id', $scope));
        $avg = $q()->whereIn('status', [PerformanceReview::SUBMITTED, PerformanceReview::FINALIZED])->avg('rating');

        return [
            'total' => $q()->count(),
            'submitted' => $q()->where('status', PerformanceReview::SUBMITTED)->count(),
            'finalized' => $q()->where('status', PerformanceReview::FINALIZED)->count(),
            'average' => $avg === null ? null : round((float) $avg, 1),
        ];
    }
}
