<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogBusinessActivity
{
    /** @var array<int, string> */
    private array $ignoredRoutes = [
        'login',
        'logout',
        'tickets.quick-store',
        'stop-impersonating',
    ];

    /** @var array<int, string> */
    private array $ignoredPrefixes = [
        'admin.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Auth::check()) {
            return $response;
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $user = Auth::user();
        $routeName = $request->route()?->getName();

        if (! $routeName || in_array($routeName, $this->ignoredRoutes, true)) {
            return $response;
        }

        if ($user->role === 'super_admin') {
            foreach ($this->ignoredPrefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return $response;
                }
            }
        }

        if (! $user->business_id && $user->role !== 'super_admin') {
            return $response;
        }

        AuditLog::log(
            AuditLog::routeAction($routeName),
            AuditLog::routeDescription($user, $request),
            $user->business_id
        );

        return $response;
    }
}
