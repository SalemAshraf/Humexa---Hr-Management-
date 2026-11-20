<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeProfilePhotoRequest extends FormRequest
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
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:' . implode(',', config('employee.allowed_profile_photo_mimes', ['jpg', 'jpeg', 'png'])),
                'max:' . config('employee.profile_photo_max_kb', 5120),
            ],
        ];
    }
}
