<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocumentType extends Model
{
    use SoftDeletes;

    protected $hidden = ['tenant_id'];

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'document_type_id');
    }
}
