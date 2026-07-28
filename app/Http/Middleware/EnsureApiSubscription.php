<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiTenantContext;
use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiSubscription
{
  public function __construct(private PlatformSettingsService $platformSettings)
  {
  }

  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if ($user && in_array($user->role, ['super_admin', 'platform_staff'], true)) {
      return $this->jsonError('Platform admin accounts cannot use the mobile API.', 403);
    }

    $business = app(ApiTenantContext::class)->business() ?? $user?->business;

    if ($business && $this->platformSettings->businessIsLocked($business)) {
      return response()->json([
        'success' => false,
        'message' => 'Subscription expired. Please renew to continue.',
        'code' => 'SUBSCRIPTION_EXPIRED',
      ], 403);
    }

    return $next($request);
  }

  private function jsonError(string $message, int $status): Response
  {
    return response()->json([
      'success' => false,
      'message' => $message,
    ], $status);
  }
}
