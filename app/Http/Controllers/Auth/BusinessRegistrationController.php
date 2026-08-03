<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegistrationClosedException;
use App\Http\Controllers\Controller;
use App\Services\BusinessRegistrationService;
use App\Services\PlatformSettingsService;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationController extends Controller
{
    public function __construct(
        private PlatformSettingsService $platformSettings,
        private BusinessRegistrationService $registration,
        private RegistrationFunnelService $funnelService,
    ) {
    }

    public function showRegistrationForm(Request $request)
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            return redirect()->route('landing.index')
                ->with('error', __('auth.register_closed'));
        }

        $this->funnelService->track($request, 'register_form_view');

        return view('auth.register-business', [
            'platformSettings' => $this->platformSettings->all(),
        ]);
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        try {
            $payload = $this->registration->validatePayload($request);
            $result = $this->registration->sendVerificationCode($request, $payload);

            return response()->json([
                'message' => $result['message'],
                'phone_display' => $result['phone_display'],
            ]);
        } catch (RegistrationClosedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function register(Request $request)
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
                'web'
            );

            $pendingMessage = $result['message'];

            if ($request->expectsJson()) {
                return response()->json([
                    'redirect' => route('landing.index'),
                    'message' => $pendingMessage,
                    'pending_approval' => true,
                ]);
            }

            return redirect()->route('landing.index')->with('info', $pendingMessage);
        } catch (RegistrationClosedException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return redirect()->route('landing.index')
                ->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()
                ->withInput()
                ->withErrors($e->errors());
        }
    }
}
