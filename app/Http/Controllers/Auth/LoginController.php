<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Services\LoginSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private LoginSecurityService $security)
    {
    }

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
        $account = User::where('email', $loginEmail)->first();

        if ($ipBlockedUntil = $this->security->ipBlockedUntil($request->ip())) {
            FailedLoginAttempt::record($credentials['email'], $request->ip(), $request->userAgent());

            return $this->errorResponse(__('auth.ip_blocked', ['time' => $ipBlockedUntil->format('d M Y, H:i')]));
        }

        if (! Auth::attempt(['email' => $loginEmail, 'password' => $credentials['password']])) {
            $result = $this->security->registerFailure($loginEmail, $account, $request->ip(), $request->userAgent());

            if ($result['ip_blocked_until']) {
                return $this->errorResponse(__('auth.ip_blocked', ['time' => $result['ip_blocked_until']->format('d M Y, H:i')]));
            }

            return $this->errorResponse(__('auth.invalid_credentials'));
        }

        $user = Auth::user();
        $user->clearLoginLock();

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

    private function errorResponse(string $message): \Illuminate\Http\RedirectResponse
    {
        return back()->withErrors(['email' => $message])->onlyInput('email');
    }
}
