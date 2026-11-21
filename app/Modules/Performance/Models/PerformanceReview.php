<?php

declare(strict_types=1);

namespace App\Modules\Performance\Models;

use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const FINALIZED = 'finalized';

    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'review_cycle_id', 'employee_id', 'reviewer_user_id', 'rating', 'strengths', 'improvements', 'goals', 'status', 'finalized_at'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'finalized_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'review_cycle_id');
    }
}
