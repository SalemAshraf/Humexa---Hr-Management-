<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateStageEvent extends Model
{
    protected $hidden = ['tenant_id'];

    protected $fillable = ['tenant_id', 'candidate_id', 'from_stage', 'to_stage', 'note', 'user_id'];
}
