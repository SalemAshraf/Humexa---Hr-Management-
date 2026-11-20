<?php

declare(strict_types=1);

namespace App\Modules\Employees\Http\Requests;

class StoreEmployeeRequest extends EmployeeRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }
}
