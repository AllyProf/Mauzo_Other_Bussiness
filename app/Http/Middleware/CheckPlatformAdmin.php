<?php

namespace App\Http\Middleware;

use App\Services\PlatformAdminService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlatformAdmin
{
    public function __construct(private PlatformAdminService $platformAdmin)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->platformAdmin->isPlatformAdmin($user)) {
            abort(403, 'Platform admin access required.');
        }

        if (! $this->platformAdmin->ipAllowed($request)) {
            abort(403, 'Your IP address is not allowed to access the admin area.');
        }

        $routeName = $request->route()?->getName() ?? '';

        if ($routeName && ! $this->platformAdmin->canAccessRoute($user, $routeName)) {
            abort(403, 'You do not have permission for this admin section.');
        }

        return $next($request);
    }
}
