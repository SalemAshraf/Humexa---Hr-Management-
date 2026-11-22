<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth 2.1 (authorization code + PKCE) for the read-only AI connector.
 * Codes and tokens are stored as hashes and are bound to one company (tenant_id), one user and one company host.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_oauth_clients', function (Blueprint $t) {
            $t->id();
            $t->string('client_id', 64)->unique();
            $t->string('name', 100);
            $t->json('redirect_uris');
            $t->timestamps();
        });

        Schema::create('ai_oauth_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code_hash', 64)->unique();
            $t->string('client_id', 64);
            $t->string('tenant_id', 64)->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('redirect_uri', 500);
            $t->string('code_challenge', 128);
            $t->string('host', 255);
            $t->dateTime('expires_at');
            $t->dateTime('used_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_oauth_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('family', 40)->index();
            $t->string('access_hash', 64)->unique();
            $t->string('refresh_hash', 64)->unique();
            $t->string('client_id', 64);
            $t->string('tenant_id', 64)->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('host', 255);
            $t->dateTime('access_expires_at');
            $t->dateTime('refresh_expires_at');
            $t->dateTime('rotated_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->dateTime('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_oauth_tokens');
        Schema::dropIfExists('ai_oauth_codes');
        Schema::dropIfExists('ai_oauth_clients');
    }
};
