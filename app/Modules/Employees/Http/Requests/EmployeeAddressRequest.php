<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeAddressRequest extends FormRequest
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
            'address_type' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:100'],
            'is_primary' => ['required', 'boolean'],
        ];
    }
}
