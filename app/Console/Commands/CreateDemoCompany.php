<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employees\Models\Employee;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Database\Seeders\TenantDemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates ONE new, clearly fictional demo company with a demo owner login and sample data.
 * Additive only: it never deletes, resets or edits anything, and it refuses to run when the
 * login email or the company name already exists. The password is read from a file (never
 * an argument, never printed). No message is sent to anyone. Not a platform admin.
 */
class CreateDemoCompany extends Command
{
    protected $signature = 'humexa:demo-company
        {--email= : Login email for the demo owner (use a fictional address such as owner@demo.example.test)}
        {--password-file= : Path to a file that holds the password (one line)}
        {--name=Demo Company (sample data) : Company name}
        {--active-days=90 : Days the demo company stays active before it becomes view-only}
        {--confirm : Required, because this adds a company to this database}';

    protected $description = 'Add one fictional demo company with an owner login and sample data (additive, refuses if it already exists).';

    public function handle(RegisterCompanyAction $register): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $file = (string) $this->option('password-file');
        $name = trim((string) $this->option('name'));

        if ($email === '' || $file === '' || ! is_readable($file)) {
            $this->error('Give --email and a readable --password-file.');

            return self::FAILURE;
        }
        $password = rtrim((string) file_get_contents($file), "\r\n");
        if (strlen($password) < 12) {
            $this->error('The password in the file must be at least 12 characters.');

            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->warn('This adds the fictional company "'.$name.'" and a login for '.$email.'. Nothing existing is changed. Re-run with --confirm.');

            return self::FAILURE;
        }
        if (User::query()->where('email', $email)->exists() || Tenant::query()->get()->contains(fn ($t) => $t->company_name === $name)) {
            $this->error('That email or company name already exists. Nothing was changed.');

            return self::FAILURE;
        }

        $reg = $register->execute($name, 'Demo Owner', $email, $password);
        /** @var Tenant $tenant */
        $tenant = $reg['tenant'];
        $tenant->forceFill(['subscription_ends_at' => now()->addDays(max(1, (int) $this->option('active-days')))->toDateString()])->save();
        $tid = (string) $tenant->getTenantKey();

        tenancy()->initialize($tenant);
        try {
            app(TenantDemoDataSeeder::class)->run();
            $this->seedAttendance($tid);
        } finally {
            tenancy()->end();
        }

        $this->info('Demo company created. Company id: '.$tid.'. Address: '.$reg['domain'].'. Login: '.$email);
        $this->line('Next, add the other sample modules: php artisan humexa:seed-demo-modules '.$tid.' --confirm');

        return self::SUCCESS;
    }

    /** Recent attendance for the sample employees (fictional), so totals and reports have data. */
    private function seedAttendance(string $tid): void
    {
        $emps = Employee::query()->where('tenant_id', $tid)->where('employee_number', 'like', 'DEMO-%')
            ->where('employment_status', Employee::STATUS_ACTIVE)->orderBy('employee_number')->get();
        DB::transaction(function () use ($emps, $tid): void {
            for ($d = 0; $d < 14; $d++) {
                $day = Carbon::today()->subDays($d);
                if (in_array($day->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                    continue;
                }
                foreach ($emps as $i => $e) {
                    if (($i + $d) % 7 === 0) {
                        continue; // a few absences
                    }
                    $late = ($i * 3 + $d) % 5 === 0 ? 12 + $i : 0;
                    AttendanceRecord::query()->firstOrCreate(
                        ['tenant_id' => $tid, 'employee_id' => $e->id, 'attendance_date' => $day->toDateString()],
                        ['status' => 'recorded', 'total_worked_minutes' => 480 - $late, 'total_break_minutes' => 45, 'late_minutes' => $late, 'early_leave_minutes' => 0, 'overtime_minutes' => $i % 4 === 0 ? 30 : 0],
                    );
                }
            }
        });
    }
}
