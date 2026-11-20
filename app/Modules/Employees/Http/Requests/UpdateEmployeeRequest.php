<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

class UpdateEmployeeRequest extends EmployeeRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }
}
