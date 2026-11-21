<?php

declare(strict_types=1);

namespace App\Modules\Plans\Services;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

class PlanLimitReached extends RuntimeException
{
    public function render(): RedirectResponse
    {
        return back()->withErrors(['plan_limit' => $this->getMessage()]);
    }
}
