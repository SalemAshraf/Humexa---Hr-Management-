<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOpening extends Model
{
    public const TYPES = ['full_time', 'part_time', 'contract', 'internship'];
    public const STATUSES = ['draft', 'open', 'on_hold', 'closed'];

    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'title', 'department_id', 'branch_id', 'employment_type', 'positions', 'status', 'description', 'created_by_user_id'];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
