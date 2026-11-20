<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class TenantOwnedBuilder extends Builder
{
    public function update(array $values)
    {
        $this->assertTenantColumnIsNotModified(array_keys($values));

        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertTenantColumnIsNotModified([
            $column,
            ...array_keys($extra),
        ]);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertTenantColumnIsNotModified([
            $column,
            ...array_keys($extra),
        ]);

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertTenantColumnIsNotModified([
            ...array_keys($columns),
            ...array_keys($extra),
        ]);

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertTenantColumnIsNotModified([
            ...array_keys($columns),
            ...array_keys($extra),
        ]);

        return parent::decrementEach($columns, $extra);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException(
            'Bulk upsert is not allowed for tenant-owned models.'
        );
    }

    public function insert(array $values)
    {
        throw new LogicException(
            'Direct bulk inserts are not allowed for tenant-owned models.'
        );
    }

    public function insertOrIgnore(array $values)
    {
        throw new LogicException(
            'Direct bulk inserts are not allowed for tenant-owned models.'
        );
    }

    
public function insertGetId(array $values, $sequence = null)
{
    if (! tenancy()->initialized) {
        throw new LogicException(
            'Cannot insert a tenant-owned record without an initialized tenant context.'
        );
    }

    $currentTenantId = (string) tenant()->getTenantKey();

    if (
        array_key_exists('tenant_id', $values)
        && (string) $values['tenant_id'] !== $currentTenantId
    ) {
        throw new LogicException(
            'Cannot insert a tenant-owned record for another tenant.'
        );
    }

    $values['tenant_id'] = $currentTenantId;

    return $this->toBase()->insertGetId($values, $sequence);
}


    public function insertUsing(array $columns, $query)
    {
        throw new LogicException(
            'Insert using queries are not allowed for tenant-owned models.'
        );
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        throw new LogicException(
            'Insert using queries are not allowed for tenant-owned models.'
        );
    }

    public function insertOrIgnoreReturning(
        array $values,
        array $returning = ['*'],
        array|string|null $uniqueBy = null
    ) {
        throw new LogicException(
            'Direct bulk inserts are not allowed for tenant-owned models.'
        );
    }

    public function truncate()
    {
        throw new LogicException(
            'Truncating tenant-owned tables is not allowed.'
        );
    }

    private function assertTenantColumnIsNotModified(array $columns): void
    {
        foreach ($columns as $column) {
            $normalized = strtolower(
                str_replace(['`', '"', '[', ']'], '', (string) $column)
            );

            $columnName = str_contains($normalized, '.')
                ? substr($normalized, strrpos($normalized, '.') + 1)
                : $normalized;

            if ($columnName === 'tenant_id') {
                throw new LogicException(
                    'The tenant_id column cannot be modified directly.'
                );
            }
        }
    }
}
