<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserActive
{
  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if (! $user) {
      return response()->json([
        'success' => false,
        'message' => 'Unauthenticated.',
      ], 401);
    }

    if (! $user->is_active) {
      return response()->json([
        'success' => false,
        'message' => 'Your account is deactivated.',
        'code' => 'ACCOUNT_DEACTIVATED',
      ], 403);
    }

    if ($user->business?->isPendingApproval()) {
      return response()->json([
        'success' => false,
        'message' => 'Business is pending approval.',
        'code' => 'PENDING_APPROVAL',
      ], 403);
    }

    if ($user->business && ! $user->business->is_active) {
      return response()->json([
        'success' => false,
        'message' => 'Business is suspended.',
        'code' => 'BUSINESS_SUSPENDED',
      ], 403);
    }

    return $next($request);
  }
}
