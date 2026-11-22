<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $table = 'payroll_settings';

    protected $hidden = ['tenant_id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dual_control' => 'boolean'];
    }

    public static function dualControl(string $tenantId): bool
    {
        return (bool) static::query()->where('tenant_id', $tenantId)->value('dual_control');
    }
}
