<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
  protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
  {
    return response()->json([
      'success' => true,
      'message' => $message,
      'data' => $data,
    ], $status);
  }

  protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
  {
    $payload = [
      'success' => false,
      'message' => $message,
    ];

    if ($errors !== null) {
      $payload['errors'] = $errors;
    }

    return response()->json($payload, $status);
  }

  protected function forbidden(string $message = 'Forbidden'): JsonResponse
  {
    return $this->error($message, 403);
  }

  protected function unauthorized(string $message = 'Unauthenticated'): JsonResponse
  {
    return $this->error($message, 401);
  }

  protected function tenantContext(): \App\Services\Api\ApiTenantContext
  {
    return app(\App\Services\Api\ApiTenantContext::class);
  }

  protected function apiBusinessId(): int
  {
    return $this->tenantContext()->businessId();
  }

  protected function apiBusiness(): ?\App\Models\Business
  {
    return $this->tenantContext()->business();
  }

  protected function authorizeApiAny(array $abilities): ?JsonResponse
  {
    foreach ($abilities as $ability) {
      if (auth()->user()?->can($ability)) {
        return null;
      }
    }

    return $this->forbidden('You do not have permission for this action.');
  }
}
