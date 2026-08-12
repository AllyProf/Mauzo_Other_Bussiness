<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserProfileApiService
{
    public function __construct(private LocaleService $localeService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user): array
    {
        $user->loadMissing(['business', 'branch', 'role_relation', 'platformAdminRole']);

        return [
            'profile' => $this->profilePayload($user),
            'meta' => [
                'supported_locales' => collect($this->localeService->supported())
                    ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                    ->values()
                    ->all(),
                'min_password_length' => max(8, (int) platform_settings('min_password_length', 8)),
                'phone_hint' => '9 digits starting with 6, 7, or 8 (e.g. 712345678)',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(User $user, array $payload, ?UploadedFile $profileImage = null): array
    {
        $localPhone = $this->normalizeLocalPhone($payload['phone'] ?? null);
        $payload['phone'] = $localPhone;

        $validated = validator($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'locale' => ['nullable', 'string', 'in:'.implode(',', array_keys($this->localeService->supported()))],
            'remove_profile_image' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ])->validate();

        $user->name = $validated['name'];
        $newEmail = strtolower(trim($validated['email']));
        if ($user->email !== $newEmail) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
        }
        $user->phone = filled($localPhone) ? '+255'.$localPhone : null;

        if (filter_var($payload['remove_profile_image'] ?? false, FILTER_VALIDATE_BOOLEAN) && $user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->profile_image = null;
        }

        $uploaded = $profileImage ?? ($payload['profile_image'] ?? null);
        if ($uploaded instanceof UploadedFile) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $user->profile_image = $uploaded->store('profile-images', 'public');
        }

        if (array_key_exists('locale', $validated) && filled($validated['locale'] ?? null)) {
            $user->locale = $this->localeService->normalize($validated['locale']);
        }

        $user->save();

        AuditLog::log('UPDATE_PROFILE', "{$user->name} updated profile details", $user->business_id);

        return $this->show($user->fresh());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePassword(User $user, array $payload): array
    {
        $minLength = max(8, (int) platform_settings('min_password_length', 8));

        $validated = validator($payload, [
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)],
        ])->validate();

        if (filled($validated['current_password'] ?? null)
            && ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        AuditLog::log('UPDATE_PROFILE_PASSWORD', "{$user->name} changed account password", $user->business_id);

        return [
            'profile' => $this->profilePayload($user->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(User $user): array
    {
        $user->loadMissing(['business', 'branch', 'role_relation', 'platformAdminRole']);

        $isStaff = ! in_array($user->role, ['owner', 'super_admin', 'platform_staff'], true);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_local' => $this->phoneForForm($user->phone),
            'locale' => $user->locale ?? 'en',
            'role' => $user->role,
            'role_label' => $user->displayRoleName(),
            'is_staff' => $isStaff,
            'profile_image' => $user->profile_image,
            'profile_image_url' => $user->profileImageUrl(),
            'business' => $user->business && ! $user->isPlatformAdmin()
                ? ['id' => $user->business->id, 'name' => $user->business->name]
                : null,
            'branch' => $user->branch
                ? ['id' => $user->branch->id, 'name' => $user->branch->name]
                : null,
            'member_since' => $user->created_at?->toDateString(),
            'member_since_label' => $user->created_at?->format('d M, Y'),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    private function phoneForForm(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '255')) {
            return substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeLocalPhone(mixed $input): ?string
    {
        if (! filled($input)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $input) ?? '';

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits !== '' ? $digits : null;
    }
}
