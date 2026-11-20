<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use RuntimeException;

class IdentityNumberHasher
{
    public function hash(string $identityType, string $identityNumber): string
    {
        $key = (string) config('employee.identity_hash_key');

        if (strlen($key) < 32) {
            throw new RuntimeException(
                'EMPLOYEE_IDENTITY_HASH_KEY must be configured with a stable secret of at least 32 characters.'
            );
        }

        $normalized = mb_strtoupper(preg_replace('/[\s-]+/u', '', trim($identityNumber)) ?? '');

        return hash_hmac('sha256', mb_strtolower($identityType).':'.$normalized, $key);
    }
}
