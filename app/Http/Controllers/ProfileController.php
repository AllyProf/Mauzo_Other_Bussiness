<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load('role_relation');

        return view('profile.index', [
            'user' => $user,
            'isStaff' => $this->isStaffAccount($user),
            'phoneLocal' => $this->phoneForForm($user->phone),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $localPhone = $this->normalizeLocalPhone($request->input('phone'));

        $request->merge([
            'phone' => $localPhone,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'locale' => ['nullable', 'string', 'in:en,sw'],
        ]);

        $localeService = app(\App\Services\LocaleService::class);
        $user->name = $validated['name'];
        $user->phone = filled($localPhone) ? '+255'.$localPhone : null;

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $request->file('profile_image')->store('profile-images', 'public');
        }

        if ($request->filled('locale')) {
            $locale = $localeService->normalize($validated['locale']);
            $user->locale = $locale;
            session(['locale' => $locale]);
            cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));
            app()->setLocale($locale);
        }

        $user->save();
        Auth::setUser($user->fresh());

        AuditLog::log('UPDATE_PROFILE', "{$user->name} updated profile details", $user->business_id);

        return redirect()->route('profile.show')->with('success', __('common.profile_updated'));
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $minLength = max(8, (int) platform_settings('min_password_length', 8));

        $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::log('UPDATE_PROFILE_PASSWORD', "{$user->name} changed account password", $user->business_id);

        return redirect()->route('profile.show')->with('success', __('common.password_updated'));
    }

    private function isStaffAccount(User $user): bool
    {
        return ! in_array($user->role, ['owner', 'super_admin', 'platform_staff'], true);
    }

    private function phoneForForm(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '255')) {
            return substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeLocalPhone(?string $input): ?string
    {
        if (! filled($input)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $input);

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits !== '' ? $digits : null;
    }
}
