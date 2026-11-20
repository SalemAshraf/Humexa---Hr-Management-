<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeIdentityRecordRequest extends FormRequest
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
            'identity_type' => ['required', 'string', Rule::in(['national_id', 'passport'])],
            'identity_number' => ['required', 'string', 'min:4', 'max:100'],
            'issuing_country' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ];
    }
}
