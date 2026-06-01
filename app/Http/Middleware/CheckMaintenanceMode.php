<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function __construct(private PlatformSettingsService $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isMaintenanceMode()) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        if ($request->routeIs('login', 'logout')) {
            return $next($request);
        }

        if ($request->is('login', 'logout')) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => $this->settings->get('maintenance_message'),
            'platformName' => $this->settings->get('platform_name'),
        ], 503);
    }
}
