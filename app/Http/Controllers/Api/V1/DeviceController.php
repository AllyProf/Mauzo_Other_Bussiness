<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceController extends ApiController
{
    public function __construct(private InAppNotificationService $notifications)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->notifications->registerDevice(
                $request->user(),
                $business,
                $request->all()
            );

            $created = (bool) ($data['created'] ?? false);
            unset($data['created']);

            return $this->success(
                $data,
                $created ? 'Device registered.' : 'Device updated.',
                $created ? 201 : 200
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to register device: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->notifications->unregisterDevice($request->user(), urldecode($token));

        return $this->success(['unregistered' => true], 'Device unregistered.');
    }
}
