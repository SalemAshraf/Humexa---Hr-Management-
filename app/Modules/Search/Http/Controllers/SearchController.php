<?php

declare(strict_types=1);

namespace App\Modules\Search\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\JobOpening;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);
        $term = trim((string) $request->query('q', ''));
        $groups = [];

        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes(mb_substr($term, 0, 80), '\\%_').'%';

            if ($user->can('employees.view')) {
                $groups[] = ['label' => 'Employees', 'results' => Employee::query()->where('tenant_id', $tenantId)
                    ->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('employee_number', 'like', $like)
                        ->orWhere('work_email', 'like', $like)->orWhere('phone', 'like', $like)
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", [$like]))
                    ->orderBy('first_name')->limit(10)->get()
                    ->map(fn (Employee $e) => ['title' => $e->full_name, 'detail' => $e->employee_number.' · '.$e->employment_status, 'url' => '/employees/'.$e->id])->all()];

                $groups[] = ['label' => 'Organization', 'results' => collect()
                    ->merge(Department::query()->where('tenant_id', $tenantId)->where('name', 'like', $like)->limit(5)->get(['id', 'name'])->map(fn ($d) => ['title' => $d->name, 'detail' => 'Department', 'url' => '/organization']))
                    ->merge(Branch::query()->where('tenant_id', $tenantId)->where('name', 'like', $like)->limit(5)->get(['id', 'name'])->map(fn ($b) => ['title' => $b->name, 'detail' => 'Branch', 'url' => '/organization']))
                    ->merge(Position::query()->where('tenant_id', $tenantId)->where('name', 'like', $like)->limit(5)->get(['id', 'name'])->map(fn ($p) => ['title' => $p->name, 'detail' => 'Position', 'url' => '/organization']))
                    ->values()->all()];
            }

            if ($user->can('recruitment.view')) {
                $groups[] = ['label' => 'Candidates', 'results' => Candidate::query()->where('tenant_id', $tenantId)
                    ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                    ->limit(10)->get()->map(fn (Candidate $c) => ['title' => $c->name, 'detail' => ucfirst($c->stage), 'url' => '/recruitment?job='.$c->job_opening_id])->all()];
                $groups[] = ['label' => 'Job openings', 'results' => JobOpening::query()->where('tenant_id', $tenantId)->where('title', 'like', $like)
                    ->limit(10)->get()->map(fn (JobOpening $j) => ['title' => $j->title, 'detail' => ucfirst(str_replace('_', ' ', $j->status)), 'url' => '/recruitment?job='.$j->id])->all()];
            }
        }

        return Inertia::render('Company/Search/Index', [
            'term' => $term,
            'groups' => array_values(array_filter($groups, fn ($g) => count($g['results']) > 0)),
            'tooShort' => $term !== '' && mb_strlen($term) < 2,
        ]);
    }
}
