<?php

declare(strict_types=1);

namespace App\Modules\AiConnector\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-use authorization code, stored as a hash, valid for ten minutes. */
class OauthCode extends Model
{
    protected $table = 'ai_oauth_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
