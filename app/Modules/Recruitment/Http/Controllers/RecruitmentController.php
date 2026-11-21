<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\CandidateStageEvent;
use App\Modules\Recruitment\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);

        $jobId = $request->integer('job') ?: null;
        $stage = $request->query('stage');

        $jobs = JobOpening::query()->where('tenant_id', $tenantId)
            ->withCount([
                'candidates as candidates_count' => fn ($q) => $q->where('tenant_id', $tenantId),
                'candidates as active_count' => fn ($q) => $q->where('tenant_id', $tenantId)->whereNotIn('stage', ['hired', 'rejected']),
                'candidates as hired_count' => fn ($q) => $q->where('tenant_id', $tenantId)->where('stage', 'hired'),
            ])
            ->latest('id')->get();

        $candidates = Candidate::query()->where('tenant_id', $tenantId)
            ->with('job:id,title')
            ->when($jobId, fn ($q) => $q->where('job_opening_id', $jobId))
            ->when(in_array($stage, Candidate::STAGES, true), fn ($q) => $q->where('stage', $stage))
            ->latest('id')->paginate(20)->withQueryString();

        $funnel = Candidate::query()->where('tenant_id', $tenantId)
            ->when($jobId, fn ($q) => $q->where('job_opening_id', $jobId))
            ->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return Inertia::render('Company/Recruitment/Index', [
            'jobs' => $jobs,
            'candidates' => $candidates,
            'funnel' => collect(Candidate::STAGES)->map(fn ($s) => ['stage' => $s, 'total' => (int) ($funnel[$s] ?? 0)])->values(),
            'filters' => ['job' => $jobId, 'stage' => in_array($stage, Candidate::STAGES, true) ? $stage : null],
            'departments' => Department::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'options' => ['stages' => Candidate::STAGES, 'types' => JobOpening::TYPES, 'statuses' => JobOpening::STATUSES],
            'abilities' => [
                'manage' => $request->user('tenant')->can('recruitment.manage'),
                'hire' => $request->user('tenant')->can('recruitment.manage') && $request->user('tenant')->can('employees.manage'),
            ],
        ]);
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);

        JobOpening::create($this->jobData($request, $tenantId) + ['tenant_id' => $tenantId, 'created_by_user_id' => $user->id]);

        return back()->with('success', 'Job opening created.');
    }

    public function updateJob(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);
        $job = JobOpening::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('job'));

        $job->update($this->jobData($request, $tenantId));

        return back()->with('success', 'Job opening updated.');
    }

    public function destroyJob(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);
        $job = JobOpening::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('job'));

        if ($job->candidates()->where('tenant_id', $tenantId)->exists()) {
            return back()->withErrors(['job' => 'This opening has candidates. Close it instead of deleting it.']);
        }

        $job->delete();

        return back()->with('success', 'Job opening deleted.');
    }

    public function storeCandidate(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);

        $data = $this->candidateData($request, $tenantId, true);
        $job = JobOpening::query()->where('tenant_id', $tenantId)->findOrFail($data['job_opening_id']);

        abort_if($job->status === 'closed', 422, 'This opening is closed.');

        $candidate = Candidate::create($data + ['tenant_id' => $tenantId, 'stage' => 'applied']);
        $this->logStage($candidate, null, 'applied', null, $request);

        return back()->with('success', 'Candidate added.');
    }

    public function updateCandidate(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);
        $candidate = $this->candidate($tenantId, $request);

        $candidate->update($this->candidateData($request, $tenantId, false));

        return back()->with('success', 'Candidate updated.');
    }

    public function moveCandidate(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);
        $candidate = $this->candidate($tenantId, $request);

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_diff(Candidate::STAGES, ['hired']))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($candidate->stage === 'hired') {
            return back()->withErrors(['stage' => 'A hired candidate cannot be moved.']);
        }

        $from = $candidate->stage;
        $candidate->update(['stage' => $data['stage']]);
        $this->logStage($candidate, $from, $data['stage'], $data['note'] ?? null, $request);

        return back()->with('success', 'Candidate moved to '.str_replace('_', ' ', $data['stage']).'.');
    }

    public function hire(Request $request, CreateEmployeeAction $create): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);
        abort_unless($user->can('employees.manage'), 403);
        $candidate = $this->candidate($tenantId, $request);

        if ($candidate->stage !== 'offer') {
            return back()->withErrors(['stage' => 'Only a candidate at the offer stage can be hired.']);
        }

        $job = JobOpening::query()->where('tenant_id', $tenantId)->findOrFail($candidate->job_opening_id);
        $parts = preg_split('/\s+/', trim($candidate->name)) ?: [$candidate->name];
        $first = array_shift($parts);
        $last = $parts ? implode(' ', $parts) : '-';

        $employee = DB::transaction(function () use ($create, $candidate, $job, $first, $last, $tenantId, $user, $request) {
            $employee = $create->execute([
                'first_name' => $first,
                'last_name' => $last,
                'personal_email' => $candidate->email,
                'phone' => $candidate->phone,
                'department_id' => $job->department_id,
                'branch_id' => $job->branch_id,
                'employment_status' => Employee::STATUS_DRAFT,
            ], $tenantId, $user->id);

            $from = $candidate->stage;
            $candidate->update(['stage' => 'hired', 'employee_id' => $employee->id]);
            $this->logStage($candidate, $from, 'hired', 'Employee record created as draft.', $request);

            return $employee;
        });

        return redirect('/employees/'.$employee->id.'/edit')->with('success', 'Draft employee created. Complete the record to activate it.');
    }

    public function destroyCandidate(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $this->tenantUser($request);
        $candidate = $this->candidate($tenantId, $request);

        if ($candidate->employee_id !== null) {
            return back()->withErrors(['candidate' => 'A hired candidate is linked to an employee record and cannot be deleted.']);
        }

        $candidate->events()->where('tenant_id', $tenantId)->delete();
        $candidate->delete();

        return back()->with('success', 'Candidate deleted.');
    }

    private function candidate(string $tenantId, Request $request): Candidate
    {
        return Candidate::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('candidate'));
    }

    private function logStage(Candidate $candidate, ?string $from, string $to, ?string $note, Request $request): void
    {
        CandidateStageEvent::create([
            'tenant_id' => $candidate->tenant_id,
            'candidate_id' => $candidate->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'note' => $note,
            'user_id' => $request->user('tenant')?->id,
        ]);
    }

    private function jobData(Request $request, string $tenantId): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'employment_type' => ['required', Rule::in(JobOpening::TYPES)],
            'positions' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::in(JobOpening::STATUSES)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function candidateData(Request $request, string $tenantId, bool $creating): array
    {
        return $request->validate([
            'job_opening_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:60'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'expected_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
