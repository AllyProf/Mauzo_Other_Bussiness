<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\CustomerCommunicationCampaign;
use App\Services\CustomerCommunicationApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerCommunicationController extends ApiController
{
    public function __construct(private CustomerCommunicationApiService $communications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customer_communications', 'manage_customers'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $page = max(1, (int) $request->input('page', 1));
            $data = $this->communications->index($business, $page);

            return $this->success($data);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Customer communication is not available on your current plan.';

            return $this->error($message, 403, $e->errors());
        }
    }

    public function send(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customer_communications', 'manage_customers'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->communications->send($business, $request->user(), $request->all());

            if (($data['mode'] ?? '') === 'scheduled') {
                $scheduledAt = $data['campaign']['scheduled_at_label'] ?? '';

                return $this->success(
                    $data,
                    'Message scheduled for '.$scheduledAt.' via '.$data['campaign']['channels_label'].'.',
                    201
                );
            }

            $summary = $data['sent'].' message(s) sent';
            if (($data['failed'] ?? 0) > 0) {
                $summary .= ', '.$data['failed'].' failed';
            }
            if (($data['skipped'] ?? 0) > 0) {
                $summary .= ', '.$data['skipped'].' skipped (missing contact for selected channel)';
            }

            return $this->success($data, $summary.'.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to send message: '.$e->getMessage(), 500);
        }
    }

    public function cancelCampaign(CustomerCommunicationCampaign $campaign): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customer_communications', 'manage_customers'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->communications->cancelCampaign($business, $campaign);

            return $this->success($data, 'Scheduled message cancelled.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to cancel campaign: '.$e->getMessage(), 500);
        }
    }
}
