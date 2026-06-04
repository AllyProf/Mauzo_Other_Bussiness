<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBusinessOperationMode
{
    public function handle(Request $request, Closure $next, string $required = 'retail'): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'super_admin') {
            return $next($request);
        }

        $business = $user->business;

        if (! $business) {
            return $next($request);
        }

        if ($required === 'retail' && ! $business->isRetailEnabled()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This business is configured for services only.'], 403);
            }

            return redirect()
                ->route('services.categories')
                ->with('warning', 'This account is set up for services only. Use the Services menu for sales.');
        }

        if ($required === 'services' && ! $business->isServicesOperationEnabled()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Services module is not enabled for this business.'], 403);
            }

            return redirect()
                ->route('sales.index')
                ->with('warning', 'Services are not enabled for this business. Contact your administrator.');
        }

        return $next($request);
    }
}
