<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlatformMailService;
use App\Services\PlatformSettingsService;
use App\Services\PlatformSmsService;
use App\Services\RegistrationVerificationService;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationController extends Controller
{
    public function __construct(
        private PlatformSettingsService $platformSettings,
        private PlatformSmsService $platformSms,
        private PlatformMailService $platformMail,
        private RegistrationVerificationService $verificationService,
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
        if (! $this->platformSettings->isRegistrationOpen()) {
            return response()->json(['message' => __('auth.register_closed')], 403);
        }

        $payload = $this->validatedRegistrationPayload($request);
        $phone255 = $this->platformSms->formatPhoneNumber($payload['phone']);
        $code = $this->verificationService->generateCode();

        $this->verificationService->store($phone255, $payload, $code);

        if (! $this->platformSms->sendRegistrationVerification($phone255, $code)) {
            $this->verificationService->forget($phone255);

            return response()->json([
                'message' => __('auth.register_sms_failed'),
            ], 502);
        }

        if (filled($payload['email'] ?? null)) {
            $this->platformMail->sendRegistrationVerification($payload['email'], $code);
        }

        $this->funnelService->track($request, 'verification_code_sent', ['phone' => $phone255]);

        return response()->json([
            'message' => __('auth.register_code_sent'),
            'phone_display' => $this->verificationService->displayPhone($phone255),
        ]);
    }

    public function register(Request $request)
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('auth.register_closed')], 403);
            }

            return redirect()->route('landing.index')
                ->with('error', __('auth.register_closed'));
        }

        $payload = $this->validatedRegistrationPayload($request);
        $phone255 = $this->platformSms->formatPhoneNumber($payload['phone']);

        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        if (! $this->verificationService->verify($phone255, $request->input('verification_code'))) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'verification_code' => __('auth.register_invalid_code'),
                ]);
            }

            return back()
                ->withInput()
                ->withErrors(['verification_code' => __('auth.register_invalid_code')]);
        }

        $defaultPlanId = $this->platformSettings->get('default_plan_id');
        $planId = $defaultPlanId ?: Plan::query()->orderBy('price')->value('id');
        $normalizedPhone = Customer::normalizePhone($phone255);
        $loginEmail = $this->resolveRegistrationEmail($payload['email'] ?? null, $phone255);
        $businessType = config('category_templates.'.$payload['business_type'], []);
        $businessTypeLabel = $payload['business_type'] === 'other'
            ? $payload['custom_business_type']
            : ($businessType['label'] ?? $payload['business_type']);
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

        $owner = User::create([
            'name' => $payload['name'],
            'email' => $loginEmail,
            'password' => Hash::make($temporaryPassword),
            'business_id' => $business->id,
            'role' => 'owner',
        ]);

        $business->update(['owner_user_id' => $owner->id]);
        Branch::query()
            ->where('business_id', $business->id)
            ->update(['owner_user_id' => $owner->id]);

        $this->platformSms->sendRegistrationPending($business);

        $this->verificationService->forget($phone255);
        $this->funnelService->track($request, 'registration_submitted', ['business_id' => $business->id]);

        $pendingMessage = __('auth.register_pending_message');

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('landing.index'),
                'message' => $pendingMessage,
                'pending_approval' => true,
            ]);
        }

        return redirect()->route('landing.index')->with('info', $pendingMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRegistrationPayload(Request $request): array
    {
        $businessTypeKeys = array_keys(config('category_templates', []));
        $businessTypeKeys[] = 'other';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^[678]\d{8}$/'],
            'email' => 'nullable|string|email|max:255|unique:users,email|unique:businesses,email',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in(tanzania_districts($request->region))],
            'address' => 'required|string|max:1000',
            'business_type' => ['required', 'string', Rule::in($businessTypeKeys)],
            'custom_business_type' => ['required_if:business_type,other', 'nullable', 'string', 'max:255'],
        ]);

        $phone255 = $this->platformSms->formatPhoneNumber($validated['phone']);
        $normalizedPhone = Customer::normalizePhone($phone255);

        if (Business::query()->where('phone', $normalizedPhone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('auth.register_phone_taken'),
            ]);
        }

        $loginEmail = $this->resolveRegistrationEmail($validated['email'] ?? null, $phone255);

        if (User::query()->where('email', $loginEmail)->exists() || Business::query()->where('email', $loginEmail)->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('auth.register_phone_taken'),
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
            'custom_business_type' => $validated['custom_business_type'] ?? null,
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
