<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'display_name', 'timezone', 'currency', 'work_start', 'work_end', 'contact_email', 'contact_phone'];
}
