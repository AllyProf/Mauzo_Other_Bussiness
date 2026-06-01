<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private SmsService $smsService)
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
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginEmail = $this->resolveLoginEmail($credentials['email']);

        if (! $loginEmail || ! Auth::attempt(['email' => $loginEmail, 'password' => $credentials['password']])) {
            return $this->failedLoginResponse($request, $credentials['email']);
        }

        $user = Auth::user();

        if ($user->business?->isPendingApproval()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your registration is pending approval. We will notify you once your account is activated.',
            ])->onlyInput('email');
        }

        if ($user->business && ! $user->business->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your business account is suspended. Please contact support.',
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your account has been deactivated. Contact your administrator.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($user->defaultLandingUrl());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function resolveLoginEmail(string $login): ?string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($login));
        }

        $phone255 = $this->smsService->formatPhoneNumber($login);
        $normalizedPhone = Customer::normalizePhone($phone255);

        $owner = User::query()
            ->where('role', 'owner')
            ->whereHas('business', fn ($query) => $query->where('phone', $normalizedPhone))
            ->first();

        return $owner?->email;
    }

    private function failedLoginResponse(Request $request, string $login): \Illuminate\Http\RedirectResponse
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $inactive = User::where('email', strtolower(trim($login)))->where('is_active', false)->exists();
            if ($inactive) {
                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Contact your administrator.',
                ])->onlyInput('email');
            }

            $pending = User::query()
                ->where('email', strtolower(trim($login)))
                ->whereHas('business', fn ($query) => $query->where('pending_approval', true))
                ->exists();

            if ($pending) {
                return back()->withErrors([
                    'email' => 'Your registration is pending approval. We will notify you once your account is activated.',
                ])->onlyInput('email');
            }
        } else {
            $loginEmail = $this->resolveLoginEmail($login);

            if ($loginEmail) {
                $pending = User::query()
                    ->where('email', $loginEmail)
                    ->whereHas('business', fn ($query) => $query->where('pending_approval', true))
                    ->exists();

                if ($pending) {
                    return back()->withErrors([
                        'email' => 'Your registration is pending approval. We will notify you once your account is activated.',
                    ])->onlyInput('email');
                }
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }
}
