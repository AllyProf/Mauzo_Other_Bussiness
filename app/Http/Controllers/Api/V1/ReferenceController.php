<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends ApiController
{
  public function paymentMethods(Request $request): JsonResponse
  {
    $business = $this->apiBusiness();
    if (! $business) {
      return $this->error('Business not found.', 404);
    }

    $methods = collect($business->enabledPaymentMethods())->map(function (array $method) {
      return [
        'key' => $method['key'],
        'label' => $method['label'],
        'type' => $method['type'] ?? 'immediate',
        'requires_reference' => (bool) ($method['requires_reference'] ?? false),
        'providers' => $method['providers'] ?? [],
      ];
    })->values();

    return $this->success(['payment_methods' => $methods]);
  }
}
