<?php

declare(strict_types=1);

namespace App\Modules\AiConnector\Models;

use Illuminate\Database\Eloquent\Model;

/** A client (for example Claude) that registered itself by dynamic client registration. Public client, no secret. */
class OauthClient extends Model
{
    protected $table = 'ai_oauth_clients';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array'];
    }
}
