<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Validation\Rule;

class BranchRequest extends OrganizationItemRequest
{
    public function rules(): array
    {
        $branchId = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('branches', 'name')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')->ignore($branchId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')->ignore($branchId)],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
