<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Validation\Rule;

class PositionRequest extends OrganizationItemRequest
{
    public function rules(): array
    {
        $positionId = $this->route('position')?->id;

        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('positions', 'code')->where('tenant_id', $this->tenantId())->whereNull('deleted_at')->ignore($positionId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
