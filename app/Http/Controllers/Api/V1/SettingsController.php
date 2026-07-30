<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\BusinessSettingsApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends ApiController
{
    public function __construct(private BusinessSettingsApiService $settings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_business_settings', 'manage_payment_methods'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        return $this->success($this->settings->index($business));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->settings->updateProfile(
                $business,
                $request->all(),
                $request->file('logo')
            );

            return $this->success($data, 'Business profile updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update profile: '.$e->getMessage(), 500);
        }
    }

    public function updateFinance(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->settings->updateFinance($business, $request->all());

            return $this->success($data, 'Finance settings saved successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update finance settings: '.$e->getMessage(), 500);
        }
    }

    public function updateAutomation(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->settings->updateAutomation($business, $request->all());

            return $this->success($data, 'Automation and notification settings saved.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update automation settings: '.$e->getMessage(), 500);
        }
    }

    public function updateShiftRules(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->settings->updateShiftRules($business, $request->all());

            return $this->success($data, 'Sales shift rules saved successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update shift rules: '.$e->getMessage(), 500);
        }
    }

    public function updatePaymentMethods(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_payment_methods', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->settings->updatePaymentMethods($business, $request->all());

            return $this->success($data, 'Payment methods saved successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update payment methods: '.$e->getMessage(), 500);
        }
    }
}
