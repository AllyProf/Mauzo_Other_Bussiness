<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiTenantContext
{
  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();
    if ($user) {
      app(ApiTenantContext::class)->setUser($user);
    }

    return $next($request);
  }
}
