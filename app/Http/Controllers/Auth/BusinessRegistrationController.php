<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlatformSettingsService;
use App\Services\RegistrationVerificationService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationController extends Controller
{
    public function __construct(
        private PlatformSettingsService $platformSettings,
        private SmsService $smsService,
        private RegistrationVerificationService $verificationService,
    ) {
    }

    public function showRegistrationForm()
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            return redirect()->route('landing.index')
                ->with('error', 'Registration is currently closed. Please contact us.');
        }

        return view('landing.register', [
            'platformSettings' => $this->platformSettings->all(),
            'registrationOpen' => true,
        ]);
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            return response()->json(['message' => 'Registration is currently closed.'], 403);
        }

        $payload = $this->validatedRegistrationPayload($request);
        $phone255 = $this->smsService->formatPhoneNumber($payload['phone']);
        $code = $this->verificationService->generateCode();
        $platformName = $this->platformSettings->get('platform_name', 'Mauzo Link');

        $this->verificationService->store($phone255, $payload, $code);

        $message = "{$platformName}: Your verification code is {$code}. It expires in 10 minutes.";
        $result = $this->smsService->sendSms($phone255, $message);

        if (! $result['success']) {
            $this->verificationService->forget($phone255);

            return response()->json([
                'message' => 'We could not send the SMS. Please check your phone number and try again.',
            ], 502);
        }

        return response()->json([
            'message' => 'Verification code sent.',
            'phone_display' => $this->verificationService->displayPhone($phone255),
        ]);
    }

    public function register(Request $request)
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Registration is currently closed.'], 403);
            }

            return redirect()->route('landing.index')
                ->with('error', 'Registration is currently closed.');
        }

        $payload = $this->validatedRegistrationPayload($request);
        $phone255 = $this->smsService->formatPhoneNumber($payload['phone']);

        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        if (! $this->verificationService->verify($phone255, $request->input('verification_code'))) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'verification_code' => 'Invalid or expired verification code.',
                ]);
            }

            return back()
                ->withInput()
                ->withErrors(['verification_code' => 'Invalid or expired verification code.']);
        }

        $defaultPlanId = $this->platformSettings->get('default_plan_id');
        $planId = $defaultPlanId ?: Plan::query()->orderBy('price')->value('id');
        $normalizedPhone = Customer::normalizePhone($phone255);
        $loginEmail = $this->resolveRegistrationEmail($payload['email'] ?? null, $phone255);
        $businessType = config('category_templates.'.$payload['business_type'], []);
        $businessTypeLabel = $businessType['label'] ?? $payload['business_type'];
        $temporaryPassword = User::generateRandomPassword(
            max(8, (int) $this->platformSettings->get('min_password_length', 8))
        );

        $business = Business::create([
            'name' => $payload['name'].' - '.$businessTypeLabel,
            'email' => $loginEmail,
            'phone' => $normalizedPhone,
            'contact_person' => $payload['name'],
            'region' => $payload['region'],
            'district' => $payload['district'],
            'address' => $payload['address'],
            'plan_id' => $planId,
            'expiry_date' => null,
            'is_active' => false,
            'pending_approval' => true,
            'category_business_types' => [[
                'key' => $payload['business_type'],
                'label' => $businessTypeLabel,
                'categories' => $businessType['categories'] ?? [],
            ]],
        ]);

        \App\Models\Branch::createDefaultForBusiness($business);

        User::create([
            'name' => $payload['name'],
            'email' => $loginEmail,
            'password' => Hash::make($temporaryPassword),
            'business_id' => $business->id,
            'role' => 'owner',
        ]);

        $this->verificationService->forget($phone255);

        $pendingMessage = 'Thank you! Your registration was received and is pending review. Once approved, your login password will be sent to your phone by SMS.';

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('login'),
                'message' => $pendingMessage,
                'pending_approval' => true,
            ]);
        }

        return redirect()->route('login')->with('info', $pendingMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRegistrationPayload(Request $request): array
    {
        $businessTypeKeys = array_keys(config('category_templates', []));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^[678]\d{8}$/'],
            'email' => 'nullable|string|email|max:255|unique:users,email|unique:businesses,email',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in(tanzania_districts($request->region))],
            'address' => 'required|string|max:1000',
            'business_type' => ['required', 'string', Rule::in($businessTypeKeys)],
        ]);

        $phone255 = $this->smsService->formatPhoneNumber($validated['phone']);
        $normalizedPhone = Customer::normalizePhone($phone255);

        if (Business::query()->where('phone', $normalizedPhone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        $loginEmail = $this->resolveRegistrationEmail($validated['email'] ?? null, $phone255);

        if (User::query()->where('email', $loginEmail)->exists() || Business::query()->where('email', $loginEmail)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        return [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'region' => $validated['region'],
            'district' => $validated['district'],
            'address' => $validated['address'],
            'business_type' => $validated['business_type'],
        ];
    }

    private function resolveRegistrationEmail(?string $email, string $phone255): string
    {
        if (filled($email)) {
            return strtolower(trim($email));
        }

        return $phone255.'@phone.mauzolink.local';
    }
}
