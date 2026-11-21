<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Leave\Services\LeaveService;
use App\Modules\Settings\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use InteractsWithTenant;

    public function index(): Response
    {
        $tenantId = $this->tenantId();
        $s = CompanySetting::query()->where('tenant_id', $tenantId)->first();

        return Inertia::render('Company/Settings/Index', [
            'settings' => [
                'display_name' => $s?->display_name ?? (string) (tenant()->company_name ?? ''),
                'timezone' => $s?->timezone ?? 'Africa/Cairo',
                'currency' => $s?->currency ?? 'EGP',
                'work_start' => substr((string) ($s?->work_start ?? '09:00'), 0, 5),
                'work_end' => substr((string) ($s?->work_end ?? '17:00'), 0, 5),
                'contact_email' => $s?->contact_email,
                'contact_phone' => $s?->contact_phone,
            ],
            'weekend_days' => app(LeaveService::class)->weekendDays($tenantId),
            'timezones' => ['Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Amman', 'Europe/London', 'UTC'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ]);
        $data['currency'] = strtoupper($data['currency']);

        CompanySetting::query()->updateOrCreate(['tenant_id' => $tenantId], $data);

        return back()->with('status', 'Settings saved.');
    }
}
