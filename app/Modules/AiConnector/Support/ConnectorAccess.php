<?php

declare(strict_types=1);

namespace App\Modules\AiConnector\Support;

use App\Models\User;

/**
 * Who may connect an AI app, and what each tool needs. The permissions mirror
 * the company dashboard blocks, so the connector never shows more than the
 * same person already sees in Afrad.
 */
final class ConnectorAccess
{
    public const SCOPE = 'hr:read';

    /** @var array<string, string> tool name => permission */
    public const TOOL_PERMISSIONS = [
        'workforce_headcount' => 'employees.manage',
        'attendance_today' => 'attendance.view',
        'leave_overview' => 'leave.manage',
        'open_positions' => 'recruitment.view',
    ];

    public static function eligible(?User $user): bool
    {
        if (! $user instanceof User || ! tenancy()->initialized) {
            return false;
        }

        if ($user->account_type !== User::ACCOUNT_TYPE_TENANT || $user->disabled_at !== null
            || $user->tenant_id === null || ! hash_equals((string) tenant()->getTenantKey(), (string) $user->tenant_id)) {
            return false;
        }

        if ((string) tenant()->getAttribute('status') !== 'active') {
            return false;
        }

        return self::allowedTools($user) !== [];
    }

    /** @return list<string> */
    public static function allowedTools(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::TOOL_PERMISSIONS),
            fn (string $tool) => $user->can(self::TOOL_PERMISSIONS[$tool]),
        ));
    }

    public static function base(\Illuminate\Http\Request $r): string
    {
        return ($r->isSecure() || config('app.force_https') ? 'https' : $r->getScheme()).'://'.$r->getHttpHost();
    }
}
