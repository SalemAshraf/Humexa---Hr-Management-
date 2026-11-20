<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use App\Shared\Tenancy\Builders\TenantOwnedBuilder;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;

#[UseEloquentBuilder(TenantOwnedBuilder::class)]
class TenantOwnedTestModel extends Model
{
    use BelongsToTenant;

    protected $table = 'users';

    protected $guarded = [];
}
