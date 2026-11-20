<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null
            && ! tenancy()->initialized;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
