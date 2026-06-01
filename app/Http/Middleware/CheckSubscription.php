<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function __construct(private PlatformSettingsService $platformSettings)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        if ($user && $user->business) {
            if ($this->platformSettings->businessIsLocked($user->business)) {
                if (! $request->is('subscription-expired')) {
                    return redirect()->route('subscription.expired');
                }
            }
        }

        return $next($request);
    }
}
