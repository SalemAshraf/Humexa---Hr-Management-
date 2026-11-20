<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToTenant
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            config('tenancy.tenant_model'),
            'tenant_id'
        );
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::saving(function (Model $model): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Cannot save a tenant-owned model without an initialized tenant context.'
                );
            }

            $currentTenantId = (string) tenant()->getTenantKey();

            // Prevent moving an existing record between tenants.
            if ($model->exists) {
                $originalTenantId = (string) $model->getRawOriginal('tenant_id');

                if ($originalTenantId !== $currentTenantId) {
                    throw new LogicException(
                        'Cannot modify a tenant-owned model outside its original tenant context.'
                    );
                }
            }

            // Never trust tenant_id supplied by the frontend or caller.
            $model->setAttribute('tenant_id', $currentTenantId);
        });

        static::deleting(function (Model $model): void {
            if (! tenancy()->initialized) {
                throw new LogicException(
                    'Cannot delete a tenant-owned model without an initialized tenant context.'
                );
            }

            $currentTenantId = (string) tenant()->getTenantKey();
            $originalTenantId = (string) $model->getRawOriginal('tenant_id');

            if ($originalTenantId !== $currentTenantId) {
                throw new LogicException(
                    'Cannot delete a tenant-owned model outside its original tenant context.'
                );
            }
        });
    }

    protected function setKeysForSaveQuery($query)
    {
        if (! tenancy()->initialized) {
            throw new LogicException(
                'Cannot modify a tenant-owned model without an initialized tenant context.'
            );
        }

        $currentTenantId = (string) tenant()->getTenantKey();

        $originalTenantId = (string) (
            $this->getRawOriginal('tenant_id') ?? ''
        );

        $modelTenantId = (string) $this->getAttribute('tenant_id');

        if (
            $originalTenantId !== $currentTenantId
            || $modelTenantId !== $currentTenantId
        ) {
            throw new LogicException(
                'Cannot modify a tenant-owned model outside its original tenant context.'
            );
        }

        $query->where(
            $this->getKeyName(),
            '=',
            $this->getKeyForSaveQuery()
        );

        $query->where(
            $this->qualifyColumn('tenant_id'),
            '=',
            $currentTenantId
        );

        return $query;
    }
}
