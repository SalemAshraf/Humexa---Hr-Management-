<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeDocumentRequest extends FormRequest
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
        $tenantId = tenancy()->initialized ? tenant()->getTenantKey() : null;

        return [
            'document_type_id' => [
                'required',
                'integer',
                Rule::exists('employee_document_types', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:190'],
            'file' => ['required', 'file', 'max:' . config('employee.document_max_kb', 10240), 'mimes:' . implode(',', config('employee.allowed_document_mimes', ['pdf', 'jpg', 'jpeg', 'png']))],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
