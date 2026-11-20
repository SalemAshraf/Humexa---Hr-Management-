<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeEmergencyContactRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:190'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
