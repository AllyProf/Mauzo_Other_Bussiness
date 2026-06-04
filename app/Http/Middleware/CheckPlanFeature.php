<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(private PlanFeatureService $planFeatures) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'super_admin') {
            return $next($request);
        }

        $feature = $this->planFeatures->featureForRoute($request->route()?->getName());

        if ($feature && ! $this->planFeatures->userHasFeature($user, $feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This module is not included in your subscription plan.',
                ], 403);
            }

            return redirect()
                ->route('subscription.upgrade')
                ->with('warning', 'Your current plan does not include '.$this->planFeatures->label($feature).'. Upgrade to unlock it.');
        }

        return $next($request);
    }
}
