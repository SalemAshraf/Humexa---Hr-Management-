<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Validation\Rule;

class DepartmentRequest extends OrganizationItemRequest
{
    public function rules(): array
    {
        $departmentId = $this->route('department');

        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->tenantId())],
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')->ignore($departmentId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
