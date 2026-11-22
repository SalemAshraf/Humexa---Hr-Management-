<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

use App\Modules\Settings\Models\CompanySetting;
use Illuminate\Support\Carbon;

/**
 * "Now" and "today" as the company sees them. The app runs in UTC, so a Cairo check-in at
 * 01:00 would otherwise be filed under the previous calendar day. The zone is the company
 * setting, or Africa/Cairo (the default the attendance API already reports) when none is saved.
 * Only used for new records and for "today" views; stored dates are never rewritten.
 */
final class CompanyClock
{
    public const DEFAULT_TIMEZONE = 'Africa/Cairo';

    public static function timezone(string $tenantId): string
    {
        $tz = CompanySetting::query()->where('tenant_id', $tenantId)->value('timezone');

        return is_string($tz) && in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : self::DEFAULT_TIMEZONE;
    }

    public static function now(string $tenantId, ?Carbon $at = null): Carbon
    {
        return ($at ?? Carbon::now())->copy()->setTimezone(self::timezone($tenantId));
    }

    public static function today(string $tenantId, ?Carbon $at = null): string
    {
        return self::now($tenantId, $at)->toDateString();
    }
}
