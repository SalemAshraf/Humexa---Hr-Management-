<?php

declare(strict_types=1);

namespace App\Modules\AiConnector\Models;

use Illuminate\Database\Eloquent\Model;

/** One access and refresh token pair, stored as hashes. Rows of one connection share a family; refreshing rotates the row. Always look it up together with the company of the host it came in on. */
class OauthToken extends Model
{
    protected $table = 'ai_oauth_tokens';

    protected $guarded = [];

    protected $hidden = ['access_hash', 'refresh_hash', 'tenant_id'];

    protected function casts(): array
    {
        return ['access_expires_at' => 'datetime', 'refresh_expires_at' => 'datetime', 'rotated_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }
}
