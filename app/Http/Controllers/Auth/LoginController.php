<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{

    public function showLoginForm()
    {
        return view('auth.login', [
            'platformSettings' => platform_settings(),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $loginEmail = strtolower(trim($credentials['email']));

        if (! Auth::attempt(['email' => $loginEmail, 'password' => $credentials['password']])) {
            FailedLoginAttempt::record($credentials['email'], $request->ip(), $request->userAgent());

            return $this->failedLoginResponse($request, $credentials['email']);
        }

        $user = Auth::user();

        if ($user->business?->isPendingApproval()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('auth.pending_approval'),
            ])->onlyInput('email');
        }

        if ($user->business && ! $user->business->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('auth.business_suspended'),
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('auth.account_deactivated'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        AuditLog::logLogin($user);

        $isFirstLogin = $user->first_login_at === null;

        if ($isFirstLogin) {
            $user->update(['first_login_at' => now()]);
            app(\App\Services\SystemTourService::class)->queueForNextPage($user->fresh());
        }

        return redirect()->intended($user->defaultLandingUrl());
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        AuditLog::logLogout($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function failedLoginResponse(Request $request, string $login): \Illuminate\Http\RedirectResponse
    {
        $email = strtolower(trim($login));

        $inactive = User::where('email', $email)->where('is_active', false)->exists();
        if ($inactive) {
            return back()->withErrors([
                'email' => __('auth.account_deactivated'),
            ])->onlyInput('email');
        }

        $pending = User::query()
            ->where('email', $email)
            ->whereHas('business', fn ($query) => $query->where('pending_approval', true))
            ->exists();

        if ($pending) {
            return back()->withErrors([
                'email' => __('auth.pending_approval'),
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => __('auth.invalid_credentials'),
        ])->onlyInput('email');
    }
}
