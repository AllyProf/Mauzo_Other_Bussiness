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

        $request->validate([
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'locale' => ['nullable', 'string', 'in:en,sw'],
        ]);

        $phone = filled($request->phone) ? '+255'.$request->phone : null;

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $request->file('profile_image')->store('profile-images', 'public');
        }

        $user->phone = $phone;

        if ($request->filled('locale')) {
            app(\App\Services\LocaleService::class)->set($request->locale, $user);
        }

        $user->save();

        AuditLog::log('UPDATE_PROFILE', "{$user->name} updated profile contact details", $user->business_id);

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
}
