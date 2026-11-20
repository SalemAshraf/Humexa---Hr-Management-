<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    protected $hidden = ['tenant_id', 'file_path', 'disk'];

    protected $fillable = [
        'document_type_id', 'title', 'original_file_name', 'file_path',
        'disk', 'mime_type', 'file_size', 'issue_date', 'expiry_date',
        'notes', 'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'document_type_id')->withTrashed();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }
}
