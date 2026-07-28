<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Customer;
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

  public function branches(Request $request): JsonResponse
  {
    $user = $request->user();
    $ctx = $this->tenantContext();

    if ($user->role === 'owner') {
      $branches = $ctx->ownerBranches()->map(fn ($b) => [
        'id' => $b->id,
        'name' => $b->name,
        'is_default' => (bool) $b->is_default,
      ])->values();

      return $this->success(['branches' => $branches]);
    }

    if ($user->branch) {
      return $this->success(['branches' => [[
        'id' => $user->branch->id,
        'name' => $user->branch->name,
        'is_default' => (bool) $user->branch->is_default,
      ]]]);
    }

    return $this->success(['branches' => []]);
  }
}
