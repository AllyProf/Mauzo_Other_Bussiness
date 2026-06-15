<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Business management platform')">
    <title>@yield('title', 'Mauzo Link') - {{ $platformSettings['platform_name'] ?? config('app.name') }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root { --brand: #940000; --white: #ffffff; --black: #000000; }
        body {
            font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
            background: var(--white);
            color: var(--black);
        }
        .guest-topbar {
            background: var(--brand);
            color: var(--white);
            padding: 12px 0;
        }
        .guest-topbar a {
            color: var(--white);
            font-weight: 600;
            text-decoration: none;
        }
        .guest-topbar a:hover { opacity: 0.9; color: var(--white); }
        .guest-brand {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .section-heading .line {
            width: 60px;
            height: 3px;
            background-color: var(--brand);
            margin: 0 auto 16px;
        }
        .section-heading h2 {
            color: var(--black);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .section-heading p {
            color: rgba(0, 0, 0, 0.7);
            max-width: 640px;
            margin: 0 auto;
        }
        .credit-btn,
        .btn.credit-btn {
            background-color: var(--brand) !important;
            border-color: var(--brand) !important;
            color: var(--white) !important;
        }
        .credit-btn:hover,
        .btn.credit-btn:hover {
            background-color: #7a0000 !important;
            border-color: #7a0000 !important;
            color: var(--white) !important;
        }
        .register-hero {
            padding-top: 48px !important;
            padding-bottom: 32px !important;
            background: var(--white) !important;
        }
        .register-section { background: var(--white); }
        .register-card {
            background: var(--white);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            padding: 40px;
        }
        .register-section-title {
            color: var(--brand) !important;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .register-divider {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            margin: 1.75rem 0;
        }
        .optional-tag {
            font-weight: 400;
            color: var(--black);
            opacity: 0.6;
            font-size: 0.85rem;
        }
        .field-help,
        .login-link-text {
            color: var(--black);
            opacity: 0.7;
        }
        .login-link-text a,
        .resend-link {
            color: var(--brand) !important;
            font-weight: 700;
        }
        .register-card .form-control {
            border-color: rgba(0, 0, 0, 0.2);
            color: var(--black);
        }
        .register-card .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 0.15rem rgba(148, 0, 0, 0.15);
        }
        .alert-brand {
            background: var(--white);
            border: 1px solid var(--brand);
            border-left: 4px solid var(--brand);
            color: var(--black);
        }
        .phone-input-group { display: flex; align-items: stretch; }
        .phone-prefix {
            display: flex;
            align-items: center;
            padding: 0 14px;
            background: var(--white);
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-right: 0;
            border-radius: 4px 0 0 4px;
            font-weight: 700;
            color: var(--black);
            white-space: nowrap;
        }
        .phone-input-group .form-control { border-radius: 0 4px 4px 0; }
        .verification-code-input {
            letter-spacing: 0.35em;
            font-size: 1.4rem;
            text-align: center;
            font-weight: 700;
        }
        .register-progress-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.82);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-progress-card {
            width: 100%;
            max-width: 420px;
            background: var(--white);
            border-radius: 12px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
        }
        .register-progress-card h4 {
            color: var(--brand);
            margin: 18px 0 10px;
        }
        .register-progress-card p {
            color: var(--black);
            opacity: 0.75;
            min-height: 48px;
            margin-bottom: 18px;
        }
        .register-progress-spinner {
            width: 54px;
            height: 54px;
            margin: 0 auto;
            border: 4px solid rgba(148, 0, 0, 0.15);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: register-spin 0.9s linear infinite;
        }
        .register-progress-bar {
            height: 8px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 999px;
            overflow: hidden;
        }
        .register-progress-bar span {
            display: block;
            height: 100%;
            width: 0;
            background: var(--brand);
            border-radius: 999px;
            transition: width 0.6s ease;
        }
        @keyframes register-spin { to { transform: rotate(360deg); } }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $platformName = $platformSettings['platform_name'] ?? config('app.name');
    @endphp
    <div class="guest-topbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="{{ route('login') }}" class="guest-brand">
                <i class="fa fa-shopping-cart"></i> {{ $platformName }}
            </a>
            <div>
                <a href="{{ route('login') }}" class="mr-3">Sign In</a>
                @if($registrationOpen ?? true)
                <a href="{{ route('register.business') }}">Register</a>
                @endif
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="container mt-3">
            <div class="alert alert-danger mb-0">{{ session('error') }}</div>
        </div>
    @endif
    @if(session('lead_success'))
        <div class="container mt-3">
            <div class="alert alert-success mb-0">{{ session('lead_success') }}</div>
        </div>
    @endif

    @yield('content')

    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
