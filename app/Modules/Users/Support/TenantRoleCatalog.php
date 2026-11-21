<?php

declare(strict_types=1);

namespace App\Modules\Users\Support;

/**
 * Default permissions and roles every company gets.
 *
 * The Employee role has no company-wide permission on purpose: it is for
 * self-service accounts that may only reach their own data.
 */
final class TenantRoleCatalog
{
    public const OWNER = 'Owner';
    public const HR_ADMIN = 'HR Admin';
    public const MANAGER = 'Manager';
    public const EMPLOYEE = 'Employee';

    /** @var list<string> */
    public const PERMISSIONS = [
        'employees.view',
        'employees.manage',
        'organization.manage',
        'attendance.view',
        'attendance.manage',
        'leave.view',
        'leave.manage',
        'leave.approve',
        'reports.view',
        'roles.manage',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            self::OWNER => self::PERMISSIONS,
            self::HR_ADMIN => array_values(array_diff(self::PERMISSIONS, ['roles.manage'])),
            self::MANAGER => [
                'employees.view',
                'attendance.view',
                'leave.view',
                'leave.approve',
                'reports.view',
            ],
            self::EMPLOYEE => [],
        ];
    }
}
