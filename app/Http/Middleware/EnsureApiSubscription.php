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
    if ($this->isExemptRoute($request)) {
      return $next($request);
    }

    $user = $request->user();

    if ($user && in_array($user->role, ['super_admin', 'platform_staff'], true)) {
      return $this->jsonError('Platform admin accounts cannot use the mobile API.', 403);
    }

    $business = app(ApiTenantContext::class)->business() ?? $user?->business;

    if ($business && $this->platformSettings->businessIsLocked($business)) {
      return response()->json([
        'success' => false,
        'message' => 'Subscription ended. Pay to continue using MauzoLink.',
        'code' => 'SUBSCRIPTION_EXPIRED',
      ], 403);
    }

    if ($business && ! $business->is_active) {
      return response()->json([
        'success' => false,
        'message' => 'Business is suspended.',
        'code' => 'BUSINESS_SUSPENDED',
      ], 403);
    }

    return $next($request);
  }

  private function isExemptRoute(Request $request): bool
  {
    return $request->is(
      'api/v1/auth/me',
      'api/v1/auth/logout',
      'api/v1/auth/switch-business',
      'api/v1/auth/switch-branch',
      'api/v1/settings',
      'api/v1/settings/*',
      'api/v1/subscription',
      'api/v1/subscription/*',
      'api/v1/notifications',
      'api/v1/notifications/*',
      'api/v1/profile',
      'api/v1/profile/*',
      'api/v1/devices',
      'api/v1/devices/*',
    );
  }

  private function jsonError(string $message, int $status): Response
  {
    return response()->json([
      'success' => false,
      'message' => $message,
    ], $status);
  }
}
