<?php

declare(strict_types=1);

namespace App\Modules\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCycle extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'name', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
