<?php

declare(strict_types=1);

namespace App\\Modules\\Employees\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class EmployeeDocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('tenant');

        return tenancy()->initialized
            && $user !== null
            && $user->account_type === 'tenant'
            && (string) $user->tenant_id === (string) tenant()->getTenantKey();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
