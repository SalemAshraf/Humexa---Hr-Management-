<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    public const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'job_opening_id', 'name', 'email', 'phone', 'source', 'stage', 'rating', 'expected_salary', 'notes', 'employee_id'];

    protected function casts(): array
    {
        return ['expected_salary' => 'float', 'rating' => 'integer'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CandidateStageEvent::class);
    }
}
