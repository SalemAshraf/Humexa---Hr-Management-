<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = ['department_id', 'name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['department_id' => 'integer', 'is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
