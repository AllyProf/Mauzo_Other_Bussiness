<?php

namespace App\Services;

use App\Exceptions\RegistrationClosedException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationService
{
    public function __construct(
        private PlatformSettingsService $platformSettings,
        private PlatformSmsService $platformSms,
        private PlatformMailService $platformMail,
        private RegistrationVerificationService $verificationService,
        private RegistrationFunnelService $funnelService,
    ) {
    }

    public function assertRegistrationOpen(): void
    {
        if (! $this->platformSettings->isRegistrationOpen()) {
            throw new RegistrationClosedException(__('auth.register_closed'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $templates = config('category_templates', []);

        return [
            'registration_open' => $this->platformSettings->isRegistrationOpen(),
            'platform_name' => (string) $this->platformSettings->get('platform_name', config('app.name')),
            'regions' => tanzania_regions(),
            'locations' => tanzania_districts(),
            'business_types' => collect($templates)->map(fn (array $type, string $key) => [
                'key' => $key,
                'label' => (string) ($type['label'] ?? $key),
                'icon' => (string) ($type['icon'] ?? 'fa-store'),
            ])->values()->push([
                'key' => 'other',
                'label' => 'Other',
                'icon' => 'fa-pencil',
            ])->values()->all(),
            'phone_hint' => '9 digits starting with 6, 7, or 8 (e.g. 0712345678)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePayload(Request $request): array
    {
        $businessTypeKeys = array_keys(config('category_templates', []));
        $businessTypeKeys[] = 'other';

        $region = $request->input('region');
        $districtOptions = is_string($region) && $region !== ''
            ? tanzania_districts($region)
            : [];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^[678]\d{8}$/'],
            'email' => 'nullable|string|email|max:255|unique:users,email|unique:businesses,email',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in($districtOptions)],
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

    /**
     * @return array{message: string, phone_display: string}
     */
    public function sendVerificationCode(Request $request, array $payload): array
    {
        $this->assertRegistrationOpen();

        $phone255 = $this->platformSms->formatPhoneNumber($payload['phone']);
        $code = $this->verificationService->generateCode();

        $this->verificationService->store($phone255, $payload, $code);

        if (! $this->platformSms->sendRegistrationVerification($phone255, $code)) {
            $this->verificationService->forget($phone255);

            throw ValidationException::withMessages([
                'phone' => __('auth.register_sms_failed'),
            ]);
        }

        if (filled($payload['email'] ?? null)) {
            $this->platformMail->sendRegistrationVerification($payload['email'], $code);
        }

        $this->funnelService->track($request, 'verification_code_sent', ['phone' => $phone255]);

        $result = [
            'message' => __('auth.register_code_sent'),
            'phone_display' => $this->verificationService->displayPhone($phone255),
        ];

        if (config('app.debug')) {
            $result['debug_code'] = $code;
        }

        return $result;
    }

    /**
     * @return array{business: Business, message: string, pending_approval: bool}
     */
    public function register(Request $request, array $payload, string $verificationCode): array
    {
        $this->assertRegistrationOpen();

        $phone255 = $this->platformSms->formatPhoneNumber($payload['phone']);

        if (! $this->verificationService->verify($phone255, $verificationCode)) {
            throw ValidationException::withMessages([
                'verification_code' => __('auth.register_invalid_code'),
            ]);
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

        Branch::createDefaultForBusiness($business);

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

        return [
            'business' => $business->fresh(),
            'message' => __('auth.register_pending_message'),
            'pending_approval' => true,
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
