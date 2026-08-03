<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RegistrationClosedException;
use App\Http\Controllers\Api\ApiController;
use App\Services\BusinessRegistrationService;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationController extends ApiController
{
    public function __construct(
        private BusinessRegistrationService $registration,
        private RegistrationFunnelService $funnelService,
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $this->funnelService->track($request, 'register_form_view');

        return $this->success($this->registration->options());
    }

    public function sendCode(Request $request): JsonResponse
    {
        try {
            $payload = $this->registration->validatePayload($request);
            $result = $this->registration->sendVerificationCode($request, $payload);

            $data = [
                'phone_display' => $result['phone_display'],
            ];
            if (isset($result['debug_code'])) {
                $data['debug_code'] = $result['debug_code'];
            }

            return $this->success($data, $result['message']);
        } catch (RegistrationClosedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                config('app.debug') ? $e->getMessage() : 'Could not send verification code.',
                500
            );
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $payload = $this->registration->validatePayload($request);

            $request->validate([
                'verification_code' => 'required|string|size:6',
            ]);

            $result = $this->registration->register(
                $request,
                $payload,
                (string) $request->input('verification_code'),
                'mobile'
            );

            $business = $result['business'];

            return $this->success([
                'pending_approval' => true,
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'contact_person' => $business->contact_person,
                    'phone' => $business->phone,
                    'email' => $business->email,
                    'region' => $business->region,
                    'district' => $business->district,
                ],
            ], $result['message'], 201);
        } catch (RegistrationClosedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }
}
