<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Create-only CSV import of departments and positions (organization.manage).
 * The file is checked row by row first. Nothing is saved unless every row is valid, and
 * nothing that already exists is changed or deleted: a name or code that is already used is an error.
 */
class OrganizationImportController extends Controller
{
    private const MAX_ROWS = 500;

    private const COLUMNS = [
        'departments' => ['name', 'code', 'description'],
        'positions' => ['name', 'department', 'code', 'description'],
    ];

    public function index(Request $request): Response
    {
        $entity = $this->entity($request);

        return Inertia::render('Organization/Import', [
            'entity' => $entity,
            'columns' => self::COLUMNS[$entity],
            'maxRows' => self::MAX_ROWS,
            'report' => session('import_report'),
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        $entity = $this->entity($request);
        $example = $entity === 'departments' ? ['Finance', 'FIN', ''] : ['Accountant', 'Finance', 'ACC', ''];

        return response()->streamDownload(function () use ($entity, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS[$entity]);
            fputcsv($out, $example);
            fclose($out);
        }, "afrad-{$entity}-template.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): RedirectResponse
    {
        $entity = $this->entity($request);
        $tenantId = $this->tenantId();
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt'], 'commit' => ['nullable', 'boolean']]);

        $parsed = $this->parse((string) $request->file('file')->get(), $entity);
        if (isset($parsed['error'])) {
            return back()->with('import_report', ['error' => $parsed['error']]);
        }

        $isDept = $entity === 'departments';
        $model = $isDept ? Department::class : Position::class;
        $names = $model::query()->where('tenant_id', $tenantId)->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $codes = $model::query()->where('tenant_id', $tenantId)->whereNotNull('code')->pluck('code')->map(fn ($c) => mb_strtolower(trim($c)))->all();
        $departments = Department::query()->where('tenant_id', $tenantId)->get()->keyBy(fn ($d) => mb_strtolower(trim($d->name)));

        $rows = [];
        $errors = [];
        foreach ($parsed['rows'] as $line => $row) {
            $messages = [];
            if ($row['name'] === '' || mb_strlen($row['name']) > 150) {
                $messages[] = __('The name is required and can have at most 150 characters.');
            }
            if (mb_strlen($row['code']) > 50) {
                $messages[] = __('The code can have at most 50 characters.');
            }
            $dept = null;
            if (! $isDept) {
                $dept = $departments[mb_strtolower($row['department'])] ?? null;
                if (! $dept) {
                    $messages[] = __('Department ":name" does not exist. Add it under Organization first.', ['name' => $row['department']]);
                }
            }
            $name = mb_strtolower($row['name']);
            // A position name only has to be unique inside its department, a department name inside the company.
            $key = $isDept ? $name : ($dept?->id.'|'.$name);
            $existing = $isDept ? $names : $this->positionKeys($tenantId);
            if ($name !== '' && (in_array($key, $existing, true) || in_array($key, array_column($rows, '_key'), true))) {
                $messages[] = __('":name" already exists.', ['name' => $row['name']]);
            }
            $code = mb_strtolower($row['code']);
            if ($code !== '' && (in_array($code, $codes, true) || in_array($code, array_column($rows, '_code'), true))) {
                $messages[] = __('The code :code is already used.', ['code' => $row['code']]);
            }

            if ($messages) {
                $errors[] = ['line' => $line, 'name' => $row['name'], 'messages' => $messages];

                continue;
            }

            $rows[] = ['_key' => $key, '_code' => $code, 'name' => $row['name'], 'code' => $row['code'] ?: null, 'description' => $row['description'] ?: null]
                + ($isDept ? [] : ['department_id' => $dept->id]);
        }

        $report = ['valid' => count($rows), 'total' => count($parsed['rows']), 'errors' => $errors, 'imported' => false];

        if ($request->boolean('commit') && ! $errors && $rows) {
            DB::transaction(function () use ($rows, $model, $tenantId) {
                foreach ($rows as $r) {
                    unset($r['_key'], $r['_code']);
                    $model::query()->forceCreate($r + ['tenant_id' => $tenantId, 'is_active' => true]);
                }
            });
            $report['imported'] = true;
        }

        return back()->with('import_report', $report);
    }

    /** @return list<string> "departmentId|name" of every position the company has */
    private function positionKeys(string $tenantId): array
    {
        return Position::query()->where('tenant_id', $tenantId)->get(['department_id', 'name'])
            ->map(fn ($p) => $p->department_id.'|'.mb_strtolower(trim($p->name)))->all();
    }

    private function entity(Request $request): string
    {
        $entity = (string) $request->route('entity');
        abort_unless(isset(self::COLUMNS[$entity]), 404);

        return $entity;
    }

    /** @return array{rows: array<int, array<string,string>>}|array{error: string} */
    private function parse(string $content, string $entity): array
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
        $required = $entity === 'departments' ? ['name'] : ['name', 'department'];
        $missing = array_diff($required, $header);
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
            foreach (self::COLUMNS[$entity] as $col) {
                $idx = array_search($col, $header, true);
                $row[$col] = $idx === false ? '' : trim((string) ($cells[$idx] ?? ''));
            }
            $rows[$i + 2] = $row;
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
