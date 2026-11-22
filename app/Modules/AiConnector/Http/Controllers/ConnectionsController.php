<?php

declare(strict_types=1);

namespace App\Modules\AiConnector\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\AiConnector\Models\OauthClient;
use App\Modules\AiConnector\Models\OauthToken;
use App\Modules\AiConnector\Support\ConnectorAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in user's own connected AI apps in this company: list and disconnect. */
class ConnectionsController extends Controller
{
    use InteractsWithTenant;

    public function index(Request $request): Response
    {
        $user = $this->tenantUser($request);
        $tid = $this->tenantId();

        $rows = OauthToken::query()->where('tenant_id', $tid)->where('user_id', $user->id)->whereNull('revoked_at')
            ->where('refresh_expires_at', '>', now())->orderBy('id')->get()->groupBy('family');
        $names = OauthClient::query()->whereIn('client_id', $rows->flatten()->pluck('client_id')->unique())->pluck('name', 'client_id');

        return Inertia::render('Company/AiConnector/Index', [
            'connections' => $rows->map(fn ($g, $family) => [
                'family' => (string) $family,
                'app' => $names[$g->first()->client_id] ?? 'Claude',
                'connected_at' => $g->first()->created_at?->toDateString(),
                'last_used_at' => $g->max('last_used_at')?->toDateTimeString(),
            ])->values(),
            'tools' => ConnectorAccess::allowedTools($user),
            'url' => ConnectorAccess::base($request).'/mcp',
        ]);
    }

    /** Always allowed, even for a company whose plan period has ended. */
    public function destroy(Request $request): RedirectResponse
    {
        // Read by name: the company host adds its own route parameter in front of this one.
        $family = (string) $request->route('family');
        $user = $this->tenantUser($request);
        OauthToken::query()->where('tenant_id', $this->tenantId())->where('user_id', $user->id)->where('family', $family)->update(['revoked_at' => now()]);

        return back()->with('status', 'Disconnected.');
    }
}
