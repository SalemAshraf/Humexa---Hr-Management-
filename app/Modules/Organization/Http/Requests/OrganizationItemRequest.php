<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class OrganizationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenancy()->initialized
            && $this->user('tenant') !== null
            && (string) $this->user('tenant')->tenant_id === (string) tenant()->getTenantKey();
    }

    protected function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }
}
