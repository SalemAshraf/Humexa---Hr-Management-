<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeDocument;
use App\Modules\Employees\Models\EmployeeDocumentType;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveService;
use App\Modules\Payroll\Models\EmployeeCompensation;
use App\Modules\Performance\Models\PerformanceReview;
use App\Modules\Performance\Models\ReviewCycle;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\JobOpening;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Adds fictional sample records for the newer modules to ONE named company, on top of the
 * demo organization from humexa:seed-demo. Additive and repeatable: it never deletes, resets
 * or edits existing rows, and only touches the EMP-#### employees. No messages are sent.
 */
class SeedDemoModules extends Command
{
    protected $signature = 'humexa:seed-demo-modules {tenant : Tenant id} {--confirm : Required, because it adds rows to this company}';

    protected $description = 'Add fictional leave, recruitment, performance, document and salary sample data to one company (additive, repeatable).';

    public function handle(LeaveService $leave): int
    {
        $tenant = Tenant::query()->find($this->argument('tenant'));
        if (! $tenant) {
            $this->error('No such tenant id.');

            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->warn('This adds fictional rows to company '.$tenant->getTenantKey().'. Nothing is deleted or changed. Re-run with --confirm.');

            return self::FAILURE;
        }

        $tid = (string) $tenant->getTenantKey();
        tenancy()->initialize($tenant);

        try {
            $emps = Employee::query()->where('tenant_id', $tid)->where('employee_number', 'like', 'EMP-%')
                ->whereNotIn('employment_status', [Employee::STATUS_DRAFT, Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])
                ->orderBy('employee_number')->get();
            if ($emps->count() < 6) {
                $this->error('Run humexa:seed-demo for this company first (needs the EMP-#### employees).');

                return self::FAILURE;
            }
            $added = [];

            // Recruitment
            if (! JobOpening::query()->where('tenant_id', $tid)->where('title', 'Sales Executive')->exists()) {
                $j1 = JobOpening::create(['tenant_id' => $tid, 'title' => 'Sales Executive', 'employment_type' => 'full_time', 'positions' => 2, 'status' => 'open']);
                $j2 = JobOpening::create(['tenant_id' => $tid, 'title' => 'HR Coordinator', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
                JobOpening::create(['tenant_id' => $tid, 'title' => 'Summer Intern', 'employment_type' => 'internship', 'positions' => 2, 'status' => 'on_hold']);
                foreach ([['Omar Khaled', $j1, 'applied', 4], ['Mona Saad', $j1, 'screening', 3], ['Youssef Nabil', $j1, 'interview', 5], ['Laila Fathy', $j2, 'offer', 4], ['Hany Zaki', $j2, 'rejected', 2], ['Dina Ramy', $j1, 'applied', null]] as [$n, $j, $s, $r]) {
                    Candidate::create(['tenant_id' => $tid, 'job_opening_id' => $j->id, 'name' => $n, 'email' => strtolower(str_replace(' ', '.', $n)).'@example.test', 'source' => 'Referral', 'stage' => $s, 'rating' => $r]);
                }
                $added[] = 'recruitment';
            }

            // Performance
            if (! ReviewCycle::query()->where('tenant_id', $tid)->where('name', '2026 H2 review')->exists()) {
                $c = ReviewCycle::create(['tenant_id' => $tid, 'name' => '2026 H2 review', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
                $rows = [['finalized', 4, 'Owns deliveries end to end', 'Delegate more'], ['submitted', 3, 'Good with customers', 'Reporting speed'], ['draft', null, null, null]];
                foreach ($rows as $i => [$s, $r, $st, $im]) {
                    PerformanceReview::create(['tenant_id' => $tid, 'review_cycle_id' => $c->id, 'employee_id' => $emps[$i]->id, 'status' => $s, 'rating' => $r, 'strengths' => $st, 'improvements' => $im, 'goals' => $st ? 'Lead one process improvement' : null]);
                }
                $added[] = 'performance';
            }

            // Leave: one pending request, only if none exists for the demo employee yet.
            $type = LeaveType::query()->where('tenant_id', $tid)->where('is_active', true)->where('name', 'like', 'Annual%')->first();
            if (! $type && ! LeaveType::query()->where('tenant_id', $tid)->where('name', 'Annual leave')->exists()) {
                $type = LeaveType::create(['tenant_id' => $tid, 'name' => 'Annual leave', 'annual_days' => 21, 'is_paid' => true, 'is_active' => true]);
                $added[] = 'leave type';
            }
            $who = $emps[5];
            if ($type && ! LeaveRequest::query()->where('tenant_id', $tid)->where('employee_id', $who->id)->where('reason', 'Family trip')->exists()) {
                $start = Carbon::now()->addDays(14)->startOfWeek(Carbon::SUNDAY);
                $leave->submit($tid, $who, $type, $start, $start->copy()->addDays(2), 'Family trip', null);
                $added[] = 'leave';
            }

            // Documents expiring (metadata only, no real file is attached)
            $dt = EmployeeDocumentType::query()->where('tenant_id', $tid)->where('slug', 'demo-id-copy')->first()
                ?? EmployeeDocumentType::query()->forceCreate(['tenant_id' => $tid, 'slug' => 'demo-id-copy', 'name' => 'ID copy', 'is_active' => true]);
            if (! EmployeeDocument::query()->where('tenant_id', $tid)->where('document_type_id', $dt->id)->exists()) {
                foreach ([[-12, 'ID copy (demo, expired)'], [9, 'ID copy (demo, expiring soon)'], [25, 'ID copy (demo, expiring)']] as $i => [$d, $title]) {
                    EmployeeDocument::query()->forceCreate(['tenant_id' => $tid, 'employee_id' => $emps[$i]->id, 'document_type_id' => $dt->id, 'title' => $title,
                        'original_file_name' => 'demo-placeholder.pdf', 'file_path' => 'demo/none', 'disk' => 'local', 'mime_type' => 'application/pdf', 'file_size' => 0, 'expiry_date' => today()->addDays($d)]);
                }
                $added[] = 'documents';
            }

            // Salary records for the estimator (fictional amounts)
            $sal = [30000, 12000, 8500, 45000, 5200, 15000];
            $n = 0;
            foreach ($emps as $i => $e) {
                $exists = EmployeeCompensation::query()->where('tenant_id', $tid)->where('employee_id', $e->id)->exists();
                if (! $exists) {
                    EmployeeCompensation::query()->forceCreate(['tenant_id' => $tid, 'employee_id' => $e->id, 'effective_from' => '2026-01-01',
                        'basic_salary' => $sal[$i % 6] * 0.8, 'allowances' => $sal[$i % 6] * 0.2, 'insured' => true]);
                    $n++;
                }
            }
            if ($n) {
                $added[] = "salary records ($n)";
            }

            $this->info($added ? 'Added: '.implode(', ', $added).'.' : 'Nothing to add. Everything is already there.');
            $this->line('Payroll estimates are not created here. Create a run from Payroll yourself; it stays an experimental estimate.');
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }
}
