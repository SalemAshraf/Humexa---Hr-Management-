<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Models\User;
use App\Modules\Payroll\Models\AdvanceInstallment;
use App\Modules\Payroll\Models\EmployeeAdvance;
use App\Modules\Payroll\Models\EmployeeCompensation;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\EgyptRuleDefaults;
use App\Modules\Payroll\Services\EgyptTemplate;
use App\Modules\Payroll\Services\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    use InteractsWithTenant;

    private const PAYSLIP_FIELDS = ['basic_salary', 'allowances', 'unpaid_leave_days', 'unpaid_leave_deduction', 'adjustments_earnings', 'adjustments_deductions', 'gross', 'insurable_wage', 'insurance_employee', 'insurance_employer', 'annual_net_income', 'income_tax', 'after_tax_levy', 'net_pay'];

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        // Reading never creates a row: a company without saved rules sees a blank, unsaved set.
        $rules = $this->activeRules($tenantId, create: false) ?? (new PayrollRuleSet)->forceFill(EgyptRuleDefaults::attributes());

        $employees = Employee::query()->where('tenant_id', $tenantId)
            ->whereNotIn('employment_status', [Employee::STATUS_DRAFT, Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])
            ->orderBy('first_name')->get(['id', 'tenant_id', 'employee_number', 'first_name', 'middle_name', 'last_name']);

        $latest = EmployeeCompensation::query()->where('tenant_id', $tenantId)->orderBy('effective_from')->get()->groupBy('employee_id')->map->last();

        return Inertia::render('Company/Payroll/Index', [
            'rules' => $this->rulesPayload($rules),
            'runs' => PayrollRun::query()->where('tenant_id', $tenantId)->orderByDesc('year')->orderByDesc('month')->get()->map(fn (PayrollRun $r) => [
                'id' => $r->id, 'year' => $r->year, 'month' => $r->month, 'status' => $r->status,
                'employees' => Payslip::where('payroll_run_id', $r->id)->count(),
                'net_total' => round((float) Payslip::where('payroll_run_id', $r->id)->sum('net_pay'), 2),
            ])->values(),
            'employees' => $employees->map(fn (Employee $e) => [
                'id' => $e->id, 'name' => $e->full_name, 'employee_number' => $e->employee_number,
                'compensation' => ($c = $latest->get($e->id)) ? [
                    'effective_from' => $c->effective_from->toDateString(), 'basic_salary' => $c->basic_salary, 'allowances' => $c->allowances,
                    'insured' => $c->insured, 'insurable_wage' => $c->insurable_wage, 'note' => $c->note, 'bank_name' => $c->bank_name, 'bank_account' => $c->bank_account,
                ] : null,
            ])->values(),
            'advances' => EmployeeAdvance::query()->where('tenant_id', $tenantId)->orderByDesc('id')->get()->map(fn (EmployeeAdvance $a) => [
                'id' => $a->id, 'employee_id' => $a->employee_id, 'employee' => $employees->firstWhere('id', $a->employee_id)?->full_name,
                'principal' => $a->principal, 'installment' => $a->installment, 'start' => sprintf('%04d-%02d', $a->start_year, $a->start_month),
                'reason' => $a->reason, 'status' => $a->status, 'balance' => $a->balance(),
            ])->values(),
            'abilities' => ['manage' => (bool) $request->user('tenant')?->can('payroll.manage')],
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $rules = $this->activeRules($tenantId, create: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'effective_from' => ['required', 'date'],
            'insurance_employee_rate' => ['required', 'numeric', 'between:0,1'],
            'insurance_employer_rate' => ['required', 'numeric', 'between:0,1'],
            'insurable_min' => ['required', 'numeric', 'min:0'],
            'insurable_max' => ['required', 'numeric', 'gte:insurable_min'],
            'personal_exemption_annual' => ['required', 'numeric', 'min:0'],
            'rounding_step' => ['required', 'integer', 'min:1', 'max:1000'],
            'after_tax_levy_rate' => ['required', 'numeric', 'between:0,0.5'],
            'after_tax_levy_label' => ['nullable', 'string', 'max:80'],
            'levy_base' => [\Illuminate\Validation\Rule::requiredIf(fn () => $rules->strict_inputs && (float) $request->input('after_tax_levy_rate') > 0), 'nullable', 'in:'.implode(',', EgyptTemplate::LEVY_BASES)],
            'month_days_basis' => ['required', 'integer', 'between:20,31'],
            'tax_tables_json' => ['required', 'json'],
        ]);

        $tables = json_decode($data['tax_tables_json'], true);
        $this->validateTables($tables);
        unset($data['tax_tables_json']);

        $rules->fill($data + ['tax_tables' => $tables, 'confirmed_by_user_id' => null, 'confirmed_at' => null, 'accepted_acknowledgements' => []])->save();

        return back()->with('status', 'Rules saved. Confirm them again before creating a run.');
    }

    public function confirmRules(Request $request): RedirectResponse
    {
        $rules = $this->activeRules($this->tenantId(), create: false);
        abort_if($rules === null, 404);

        if ($rules->missingSettings() !== []) {
            return back()->withErrors(['rules' => 'Enter every rate, limit and tax table in the rules before marking them as reviewed.']);
        }

        $accepted = array_values(array_intersect((array) $request->input('acknowledged', []), $rules->required_acknowledgements ?? []));
        $rules->accepted_acknowledgements = $accepted;

        if ($rules->pendingAcknowledgements() !== []) {
            return back()->withErrors(['rules' => 'Accept every open item before marking the rules as reviewed.']);
        }

        $rules->forceFill(['confirmed_by_user_id' => $request->user('tenant')->id, 'confirmed_at' => now()])->save();

        return back()->with('status', 'Rules marked as reviewed.');
    }

    /** Loads the Egypt 2026 starting rules as a new, unreviewed set. Never overwrites existing rules. */
    public function loadEgyptTemplate(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();

        if (PayrollRuleSet::query()->where('tenant_id', $tenantId)->where('template_key', EgyptTemplate::KEY)->exists()) {
            return back()->withErrors(['rules' => 'The Egypt 2026 template is already loaded.']);
        }

        PayrollRuleSet::query()->create(['tenant_id' => $tenantId] + EgyptTemplate::attributes());

        return back()->with('status', 'Egypt 2026 template loaded. Review it and accept the open items before creating a run.');
    }

    public function storeCompensation(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($request->route('employee'));

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'allowances' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'insured' => ['required', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'insurable_wage' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        EmployeeCompensation::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'effective_from' => $data['effective_from']],
            $data + ['allowances' => $data['allowances'] ?? 0, 'created_by_user_id' => $request->user('tenant')->id],
        );

        return back()->with('status', 'Salary saved.');
    }

    public function storeRun(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12']]);

        $monthEnd = \Illuminate\Support\Carbon::create((int) $data['year'], (int) $data['month'], 1)->endOfMonth()->toDateString();
        $rules = PayrollRuleSet::query()->where('tenant_id', $tenantId)->whereDate('effective_from', '<=', $monthEnd)->orderByDesc('effective_from')->orderByDesc('id')->first();

        if ($rules === null) {
            return back()->withErrors(['run' => 'No rule set is effective for that month.']);
        }

        if (! $rules->coversMonth((int) $data['year'], (int) $data['month'])) {
            return back()->withErrors(['run' => 'The payroll rules end before that month. Enter and review rules for this period first.']);
        }

        if ($rules->missingSettings() !== [] || ! $rules->isConfirmed()) {
            return back()->withErrors(['run' => 'Mark the payroll rules as reviewed before creating a run.']);
        }

        if (PayrollRun::where('tenant_id', $tenantId)->where('year', $data['year'])->where('month', $data['month'])->exists()) {
            return back()->withErrors(['run' => 'A run for that month already exists.']);
        }

        $run = PayrollRun::query()->create($data + ['tenant_id' => $tenantId, 'rule_set_id' => $rules->id, 'created_by_user_id' => $request->user('tenant')->id])->refresh();
        $skipped = app(PayrollRunService::class)->build($run);

        return redirect('/payroll/runs/'.$run->id)->with('status', $skipped === [] ? 'Run created.' : 'Run created. Left out: '.implode('; ', $skipped));
    }

    public function showRun(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $run = $this->run($request);

        $slips = Payslip::query()->where('payroll_run_id', $run->id)->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')->orderBy('id')->get();

        return Inertia::render('Company/Payroll/Run', [
            'run' => ['id' => $run->id, 'year' => $run->year, 'month' => $run->month, 'status' => $run->status, 'approved_at' => $run->approved_at?->toDateTimeString()],
            'rule_name' => PayrollRuleSet::where('tenant_id', $tenantId)->whereKey($run->rule_set_id)->value('name'),
            'provisional' => ($rs = PayrollRuleSet::where('tenant_id', $tenantId)->find($run->rule_set_id))?->strict_inputs ? [
                'valid_from' => $rs->effective_from->toDateString(), 'valid_to' => $rs->effective_to?->toDateString(), 'levy_base' => $rs->levy_base,
                'not_calculated' => EgyptTemplate::NOT_CALCULATED,
            ] : null,
            'payslips' => $slips->map(fn (Payslip $p) => ['id' => $p->id, 'employee' => $p->employee?->full_name, 'employee_number' => $p->employee?->employee_number] + $p->only(self::PAYSLIP_FIELDS))->values(),
            'totals' => ['gross' => round((float) $slips->sum('gross'), 2), 'tax' => round((float) $slips->sum('income_tax'), 2), 'insurance_employee' => round((float) $slips->sum('insurance_employee'), 2), 'insurance_employer' => round((float) $slips->sum('insurance_employer'), 2), 'net' => round((float) $slips->sum('net_pay'), 2)],
            'adjustments' => PayrollAdjustment::query()->where('payroll_run_id', $run->id)->orderBy('id')->get(['id', 'employee_id', 'kind', 'label', 'amount', 'taxable']),
            'employees' => Employee::query()->where('tenant_id', $tenantId)->whereIn('id', $slips->pluck('employee_id'))->orderBy('first_name')->get(['id', 'tenant_id', 'first_name', 'middle_name', 'last_name'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->values(),
            'abilities' => ['manage' => (bool) $request->user('tenant')?->can('payroll.manage')],
        ]);
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        $skipped = app(PayrollRunService::class)->build($run);

        return back()->with('status', $skipped === [] ? 'Recalculated.' : 'Recalculated. Left out: '.implode('; ', $skipped));
    }

    public function approve(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Already approved.');
        $approver = $request->user('tenant');

        // Two-person control: whoever created the run cannot also approve it while another payroll manager exists.
        if ((int) $run->created_by_user_id === (int) $approver->id && $this->otherPayrollManagerExists((string) $run->tenant_id, (int) $approver->id)) {
            return back()->withErrors(['run' => 'Another payroll manager must approve this run. The person who created it cannot approve it.']);
        }

        DB::transaction(function () use ($run, $approver) {
            // Recalculate first so advance installments reflect the balances at the moment of approval.
            app(PayrollRunService::class)->build($run);

            foreach (Payslip::query()->where('payroll_run_id', $run->id)->get() as $slip) {
                foreach (($slip->details['advance_installments'] ?? []) as $advanceId => $amount) {
                    AdvanceInstallment::query()->updateOrCreate(
                        ['employee_advance_id' => (int) $advanceId, 'payroll_run_id' => $run->id],
                        ['tenant_id' => $run->tenant_id, 'amount' => $amount],
                    );
                }
            }

            $run->forceFill(['status' => PayrollRun::APPROVED, 'approved_by_user_id' => $approver->id, 'approved_at' => now()])->save();
        });

        return back()->with('status', 'Run approved and locked.');
    }

    private function otherPayrollManagerExists(string $tenantId, int $exceptUserId): bool
    {
        return User::query()->where('tenant_id', $tenantId)->where('account_type', User::ACCOUNT_TYPE_TENANT)->whereNull('disabled_at')
            ->where('id', '!=', $exceptUserId)->get()->contains(fn (User $u) => $u->can('payroll.manage'));
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'principal' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'installment' => ['required', 'numeric', 'gt:0', 'lte:principal'],
            'start_year' => ['required', 'integer', 'between:2020,2100'],
            'start_month' => ['required', 'integer', 'between:1,12'],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);

        EmployeeAdvance::query()->create($data + ['tenant_id' => $tenantId, 'created_by_user_id' => $request->user('tenant')->id]);

        return back()->with('status', 'Advance saved. It is deducted from net pay in each run from the start month until repaid.');
    }

    public function cancelAdvance(Request $request): RedirectResponse
    {
        $advance = EmployeeAdvance::query()->where('tenant_id', $this->tenantId())->findOrFail($request->route('advance'));
        $advance->forceFill(['status' => 'cancelled'])->save();

        return back()->with('status', 'Advance cancelled. Installments already taken in approved runs stay on record.');
    }

    /** Plain transfer list: names, bank details as typed by the company, net pay. Not a bank-specific file format. */
    public function exportBank(Request $request): StreamedResponse
    {
        $run = $this->run($request);
        $tenantId = $this->tenantId();
        $end = \Illuminate\Support\Carbon::create((int) $run->year, (int) $run->month, 1)->endOfMonth()->toDateString();
        $slips = Payslip::query()->where('payroll_run_id', $run->id)->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')->orderBy('id')->get();
        $comps = EmployeeCompensation::query()->where('tenant_id', $tenantId)->whereDate('effective_from', '<=', $end)->orderBy('effective_from')->get()->groupBy('employee_id')->map->last();
        $name = sprintf('payroll-transfer-list-%04d-%02d.csv', $run->year, $run->month);

        return response()->streamDownload(function () use ($slips, $comps) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employee_number', 'employee', 'bank_name', 'bank_account', 'net_pay_egp', 'note']);
            foreach ($slips as $p) {
                $c = $comps->get($p->employee_id);
                $note = $c?->bank_account ? '' : 'no bank details recorded';
                fputcsv($out, array_map([$this, 'csvSafe'], [$p->employee?->employee_number, $p->employee?->full_name, $c?->bank_name, $c?->bank_account, $p->net_pay, $note]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function destroyRun(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'An approved run cannot be deleted.');

        $run->delete();

        return redirect('/payroll')->with('status', 'Draft run deleted.');
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Only a draft run can change.');

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'kind' => ['required', Rule::in(['earning', 'deduction'])],
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'taxable' => ['required', 'boolean'],
        ]);

        PayrollAdjustment::query()->create($data + ['tenant_id' => $tenantId, 'payroll_run_id' => $run->id, 'created_by_user_id' => $request->user('tenant')->id]);
        app(PayrollRunService::class)->build($run);

        return back()->with('status', 'Adjustment added.');
    }

    public function destroyAdjustment(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Only a draft run can change.');
        $adjustment = PayrollAdjustment::query()->where('payroll_run_id', $run->id)->findOrFail($request->route('adjustment'));
        $adjustment->delete();
        app(PayrollRunService::class)->build($run);

        return back()->with('status', 'Adjustment removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $run = $this->run($request);

        $rows = Payslip::query()->where('payroll_run_id', $run->id)->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')->orderBy('id')->get();
        $name = sprintf('payroll-run-%04d-%02d.csv', $run->year, $run->month);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['employee_number', 'employee'], self::PAYSLIP_FIELDS, ['note']));
            foreach ($rows as $p) {
                fputcsv($out, array_map([$this, 'csvSafe'], array_merge([$p->employee?->employee_number, $p->employee?->full_name], array_values($p->only(self::PAYSLIP_FIELDS)), [$this->exportNote($p)])));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet formula injection from names. */
    private function exportNote(Payslip $p): string
    {
        $note = 'Calculated from company-entered rules, not verified by Afrad, amounts in EGP';
        $pv = $p->details['provisional'] ?? null;

        return $pv === null ? $note : $note.'. Provisional estimate, valid '.$pv['valid_from'].' to '.($pv['valid_to'] ?? 'open').'; levy base chosen by company: '.($pv['levy_base'] ?? 'n/a').'; not calculated: '.implode('; ', $pv['not_calculated']);
    }

    public function csvSafe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    private function run(Request $request): PayrollRun
    {
        return PayrollRun::query()->where('tenant_id', $this->tenantId())->findOrFail($request->route('run'));
    }

    private function activeRules(string $tenantId, bool $create): ?PayrollRuleSet
    {
        $rules = PayrollRuleSet::query()->where('tenant_id', $tenantId)->orderByDesc('effective_from')->orderByDesc('id')->first();

        if ($rules === null && $create) {
            $rules = PayrollRuleSet::query()->create(['tenant_id' => $tenantId] + EgyptRuleDefaults::attributes());
        }

        return $rules;
    }

    private function rulesPayload(PayrollRuleSet $r): array
    {
        return $r->only(['id', 'name', 'country', 'insurance_employee_rate', 'insurance_employer_rate', 'insurable_min', 'insurable_max', 'personal_exemption_annual', 'rounding_step', 'after_tax_levy_rate', 'after_tax_levy_label', 'levy_base', 'month_days_basis', 'tax_tables', 'sources', 'open_questions', 'strict_inputs', 'template_key'])
            + ['effective_to' => $r->effective_to?->toDateString(), 'acknowledgements' => collect($r->required_acknowledgements ?? [])->map(fn ($k) => ['key' => $k, 'text' => EgyptTemplate::ACKNOWLEDGEMENTS[$k] ?? $k])->values(), 'missing' => $r->missingSettings(), 'legacy_defaults' => $r->exists && ! $r->isConfirmed() && str_contains((string) $r->name, 'ESTIMATOR (unverified starting values)')] + ['effective_from' => $r->effective_from->toDateString(), 'confirmed' => $r->isConfirmed(), 'confirmed_at' => $r->confirmed_at?->toDateTimeString()];
    }

    private function validateTables(mixed $tables): void
    {
        $fail = fn (string $m) => abort(422, 'Tax tables: '.$m);
        is_array($tables) && $tables !== [] || $fail('expected a list of tables.');

        foreach ($tables as $t) {
            is_array($t) && array_key_exists('up_to', $t) && is_array($t['bands'] ?? null) && $t['bands'] !== [] || $fail('each table needs up_to and bands.');
            $last = 0;
            foreach ($t['bands'] as $b) {
                is_array($b) && count($b) === 2 && is_numeric($b[1]) && $b[1] >= 0 && $b[1] <= 1 || $fail('each band is [upper limit, rate between 0 and 1].');
                if ($b[0] !== null) {
                    is_numeric($b[0]) && $b[0] > $last || $fail('band limits must increase.');
                    $last = $b[0];
                }
            }
            end($t['bands'])[0] === null || $fail('the last band of each table needs a null limit.');
        }

        end($tables)['up_to'] === null || $fail('the last table needs a null up_to.');
    }
}
