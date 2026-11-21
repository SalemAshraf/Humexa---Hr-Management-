<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\EmployeeDocument;
use Inertia\Inertia;
use Inertia\Response;

/** Expired and soon-to-expire employee documents, for HR only (same gate as the documents themselves). */
class DocumentExpiryController extends Controller
{
    use InteractsWithTenant;

    public const WINDOW_DAYS = 30;

    public function index(): Response
    {
        $tenantId = $this->tenantId();
        $limit = today()->addDays(self::WINDOW_DAYS);

        $rows = EmployeeDocument::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $limit)
            ->with(['employee:id,first_name,last_name,employee_number', 'documentType:id,name'])
            ->orderBy('expiry_date')
            ->limit(500)
            ->get()
            ->map(fn (EmployeeDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'type' => $d->documentType?->name,
                'employee_id' => $d->employee_id,
                'employee' => $d->employee?->full_name,
                'employee_number' => $d->employee?->employee_number,
                'expiry_date' => $d->expiry_date->toDateString(),
                'days' => (int) today()->diffInDays($d->expiry_date, false),
                'expired' => $d->expiry_date->lt(today()),
            ])->values();

        return Inertia::render('Company/Documents/Expiry', [
            'rows' => $rows,
            'window_days' => self::WINDOW_DAYS,
        ]);
    }
}
