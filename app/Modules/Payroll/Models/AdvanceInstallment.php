<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceInstallment extends Model
{
    protected $table = 'advance_installments';

    protected $guarded = ['id'];
}
