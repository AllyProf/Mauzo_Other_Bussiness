<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends ApiController
{
    public function __construct(private InAppNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $result = $this->notifications->listForUser(
            $request->user(),
            (int) $business->id,
            $request->only(['page', 'limit', 'per_page', 'unread_only'])
        );

        // Spec: data is the notification array; meta for pagination / unread count.
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $result['notifications'],
            'meta' => $result['meta'],
        ]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $data = $this->notifications->markRead(
            $request->user(),
            (int) $business->id,
            $notification
        );

        if (! $data) {
            return $this->error('Notification not found.', 404);
        }

        return $this->success($data, 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $data = $this->notifications->markAllRead($request->user(), (int) $business->id);

        return $this->success($data, 'All notifications marked as read.');
    }

    public function preferences(Request $request): JsonResponse
    {
        return $this->success($this->notifications->getPreferences($request->user()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $data = $this->notifications->updatePreferences($request->user(), $request->all());

            return $this->success($data, 'Notification preferences saved.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }
}
