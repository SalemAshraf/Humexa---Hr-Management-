<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\AttentionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request, AttentionService $attention): Response
    {
        $tenantId = $this->tenantId();
        $user = $this->tenantUser($request);

        return Inertia::render('Company/Notifications/Index', [
            'items' => $attention->items($user, $tenantId),
        ]);
    }
}
