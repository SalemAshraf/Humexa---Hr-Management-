<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Plans\Services\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import of employees (employees.manage). A file is checked row by row first;
 * nothing is saved unless every row is valid, and then all rows are saved together.
 */
class EmployeeImportController extends Controller
{
    private const MAX_ROWS = 500;

    private const COLUMNS = ['first_name', 'last_name', 'work_email', 'phone', 'hire_date', 'department', 'position', 'employee_number'];

    public function index(): Response
    {
        $tenantId = $this->tenantId();
        $usage = app(PlanLimits::class)->usage($tenantId);

        return Inertia::render('Employees/Import', [
            'columns' => self::COLUMNS,
            'maxRows' => self::MAX_ROWS,
            'report' => session('import_report'),
            'room' => ($usage['plan']['max_employees'] ?? null) === null ? null : max(0, $usage['plan']['max_employees'] - $usage['employees']),
        ]);
    }

    public function template(): StreamedResponse
    {
        abort_unless(tenancy()->initialized, 404);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS);
            fputcsv($out, ['Sara', 'Hassan', 'sara.hassan@example.com', '01000000000', '2026-01-15', 'Finance', 'Accountant', '']);
            fclose($out);
        }, 'afrad-employees-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, CreateEmployeeAction $create): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $request->validate([
            'file' => ['required', 'file', 'max:1024', 'mimes:csv,txt'],
            'commit' => ['nullable', 'boolean'],
        ]);

        $parsed = $this->parse((string) $request->file('file')->get());
        if (isset($parsed['error'])) {
            return back()->with('import_report', ['error' => $parsed['error']]);
        }

        $departments = Department::query()->where('tenant_id', $tenantId)->get()->keyBy(fn ($d) => mb_strtolower(trim($d->name)));
        $positions = Position::query()->where('tenant_id', $tenantId)->get()->keyBy(fn ($p) => mb_strtolower(trim($p->name)));
        $existingEmails = Employee::withTrashed()->where('tenant_id', $tenantId)->whereNotNull('work_email')->pluck('work_email')->map(fn ($e) => mb_strtolower($e))->all();
        $existingNumbers = Employee::withTrashed()->where('tenant_id', $tenantId)->pluck('employee_number')->map(fn ($e) => mb_strtolower((string) $e))->all();

        $rows = [];
        $errors = [];
        $seenEmails = [];
        $seenNumbers = [];

        foreach ($parsed['rows'] as $line => $row) {
            $messages = [];
            $v = Validator::make($row, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'work_email' => ['nullable', 'email', 'max:190'],
                'phone' => ['nullable', 'string', 'max:30'],
                'hire_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'employee_number' => ['nullable', 'string', 'max:50'],
            ]);
            foreach ($v->errors()->all() as $m) {
                $messages[] = $m;
            }

            $dept = null;
            if ($row['department'] !== '') {
                $dept = $departments[mb_strtolower($row['department'])] ?? null;
                if (! $dept) {
                    $messages[] = __('Department ":name" does not exist. Add it under Organization first.', ['name' => $row['department']]);
                }
            }
            $pos = null;
            if ($row['position'] !== '') {
                $pos = $positions[mb_strtolower($row['position'])] ?? null;
                if (! $pos) {
                    $messages[] = __('Position ":name" does not exist. Add it under Organization first.', ['name' => $row['position']]);
                }
            }

            $email = mb_strtolower($row['work_email']);
            if ($email !== '' && (in_array($email, $existingEmails, true) || in_array($email, $seenEmails, true))) {
                $messages[] = __('The email :email is already used.', ['email' => $row['work_email']]);
            }
            $number = mb_strtolower($row['employee_number']);
            if ($number !== '' && (in_array($number, $existingNumbers, true) || in_array($number, $seenNumbers, true))) {
                $messages[] = __('The employee number :number is already used.', ['number' => $row['employee_number']]);
            }
            if ($email !== '') {
                $seenEmails[] = $email;
            }
            if ($number !== '') {
                $seenNumbers[] = $number;
            }

            if ($messages) {
                $errors[] = ['line' => $line, 'name' => trim($row['first_name'].' '.$row['last_name']), 'messages' => $messages];

                continue;
            }

            $rows[] = [
                'line' => $line,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'work_email' => $row['work_email'] ?: null,
                'phone' => $row['phone'] ?: null,
                'hire_date' => $row['hire_date'],
                'department_id' => $dept?->id,
                'position_id' => $pos?->id,
                'employee_number' => $row['employee_number'] ?: null,
            ];
        }

        $usage = app(PlanLimits::class)->usage($tenantId);
        $max = $usage['plan']['max_employees'] ?? null;
        $planBlock = $max !== null && ($usage['employees'] + count($rows)) > $max
            ? __('Your plan allows :max employees and you have :used. This file adds :n more. Ask the platform team to change your plan.', ['max' => $max, 'used' => $usage['employees'], 'n' => count($rows)])
            : null;

        $report = ['valid' => count($rows), 'total' => count($parsed['rows']), 'errors' => $errors, 'plan_block' => $planBlock, 'imported' => false];

        if ($request->boolean('commit') && ! $errors && ! $planBlock && $rows) {
            $actorId = $request->user('tenant')?->id;
            DB::transaction(function () use ($rows, $create, $tenantId, $actorId) {
                foreach ($rows as $r) {
                    unset($r['line']);
                    $create->execute([...$r, 'employment_status' => 'active'], $tenantId, $actorId);
                }
            });
            $report['imported'] = true;
        }

        return back()->with('import_report', $report);
    }

    /** @return array{rows: array<int, array<string,string>>}|array{error: string} */
    private function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            return ['error' => __('The file must be saved as UTF-8 CSV.')];
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if (count($lines) < 2) {
            return ['error' => __('The file has no rows. Download the template and fill it in.')];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv($lines[0], $delimiter));
        $missing = array_diff(['first_name', 'last_name', 'hire_date'], $header);
        if ($missing) {
            return ['error' => __('Missing columns: :cols. Download the template to see the expected columns.', ['cols' => implode(', ', $missing)])];
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter);
            $row = [];
            foreach (self::COLUMNS as $col) {
                $idx = array_search($col, $header, true);
                $row[$col] = $idx === false ? '' : trim((string) ($cells[$idx] ?? ''));
            }
            $rows[$i + 2] = $row; // spreadsheet line number: header is line 1
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['error' => __('A file can have at most :max rows. Split it and import in parts.', ['max' => self::MAX_ROWS])];
        }
        if (! $rows) {
            return ['error' => __('The file has no rows. Download the template and fill it in.')];
        }

        return ['rows' => $rows];
    }

    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }
}
