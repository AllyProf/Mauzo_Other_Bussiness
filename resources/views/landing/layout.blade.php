<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="@yield('meta_description', 'Mauzo Link — modern point of sale for every business in Tanzania.')">
    <title>@yield('title', 'Mauzo Link') — Point of Sale</title>
    <link rel="stylesheet" href="{{ asset('landing/style.css') }}">
    <link rel="stylesheet" href="{{ asset('landing/mauzo-link.css') }}">
    @stack('styles')
</head>
<body>
@php
    $platformName = $platformSettings['platform_name'] ?? 'Mauzo Link';
    $supportEmail = $platformSettings['support_email'] ?? 'support@mauzolink.com';
    $supportPhone = $platformSettings['support_phone'] ?? '';
    $currency = $platformSettings['currency_symbol'] ?? 'TZS';
@endphp

<div class="preloader d-flex align-items-center justify-content-center">
    <div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>
</div>

<header class="header-area">
    <div class="top-header-area">
        <div class="container h-100">
            <div class="row h-100 align-items-center">
                <div class="col-12 d-flex justify-content-between flex-wrap">
                    <div class="logo">
                        <a href="{{ route('landing.index') }}">
                            <img src="{{ asset('landing/img/core-img/logo.png') }}" alt="{{ $platformName }}">
                        </a>
                    </div>
                    <div class="top-contact-info d-flex align-items-center flex-wrap">
                        <a href="#"><img src="{{ asset('landing/img/core-img/placeholder.png') }}" alt=""> <span>Tanzania</span></a>
                        @if($supportPhone)
                        <a href="tel:{{ $supportPhone }}"><img src="{{ asset('landing/img/core-img/call2.png') }}" alt=""> <span>{{ $supportPhone }}</span></a>
                        @endif
                        <a href="mailto:{{ $supportEmail }}"><img src="{{ asset('landing/img/core-img/message.png') }}" alt=""> <span>{{ $supportEmail }}</span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="credit-main-menu" id="sticker">
        <div class="classy-nav-container breakpoint-off">
            <div class="container">
                <nav class="classy-navbar justify-content-between" id="creditNav">
                    <div class="classy-navbar-toggler">
                        <span class="navbarToggler"><span></span><span></span><span></span></span>
                    </div>
                    <div class="classy-menu">
                        <div class="classycloseIcon"><div class="cross-wrap"><span class="top"></span><span class="bottom"></span></div></div>
                        <div class="classynav">
                            <ul>
                                <li class="{{ request()->routeIs('landing.index') ? 'active' : '' }}"><a href="{{ route('landing.index') }}">Home</a></li>
                                <li><a href="{{ route('landing.index') }}#features">Features</a></li>
                                <li><a href="{{ route('landing.index') }}#pricing">Pricing</a></li>
                                @if($registrationOpen ?? true)
                                <li class="{{ request()->routeIs('register.business') ? 'active' : '' }}"><a href="{{ route('register.business') }}">Register</a></li>
                                @endif
                                <li><a href="{{ route('login') }}">Login</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="contact d-none d-lg-block">
                        @if($registrationOpen ?? true)
                        <a href="{{ route('register.business') }}" class="credit-btn-nav btn credit-btn btn-sm px-3">Start Free Trial</a>
                        @else
                        <a href="{{ route('login') }}" class="credit-btn-nav btn credit-btn btn-sm px-3">Sign In</a>
                        @endif
                    </div>
                </nav>
            </div>
        </div>
    </div>
</header>

@if(session('success') || session('error') || session('info'))
<div class="container mt-3">
    @if(session('success'))<div class="alert alert-success mb-0">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger mb-0">{{ session('error') }}</div>@endif
    @if(session('info'))<div class="alert alert-info mb-0">{{ session('info') }}</div>@endif
</div>
@endif

@yield('content')

<footer class="footer-area section-padding-100-0">
    <div class="container">
        <div class="row">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="single-footer-widget mb-100">
                    <h5 class="widget-title">{{ $platformName }}</h5>
                    <p class="text-muted" style="color:#aaa!important;line-height:1.7;">
                        Modern point-of-sale for shops, spare parts, retail and services. Sell smarter, track stock, manage shifts and grow your business.
                    </p>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="single-footer-widget mb-100">
                    <h5 class="widget-title">Quick Links</h5>
                    <nav><ul>
                        <li><a href="{{ route('landing.index') }}">Home</a></li>
                        <li><a href="{{ route('landing.index') }}#features">Features</a></li>
                        <li><a href="{{ route('landing.index') }}#pricing">Pricing Plans</a></li>
                        @if($registrationOpen ?? true)
                        <li><a href="{{ route('register.business') }}">Register Your Business</a></li>
                        @endif
                        <li><a href="{{ route('login') }}">Login</a></li>
                    </ul></nav>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="single-footer-widget mb-100">
                    <h5 class="widget-title">Contact</h5>
                    <p style="color:#aaa!important;"><i class="fa fa-envelope"></i> {{ $supportEmail }}</p>
                    @if($supportPhone)<p style="color:#aaa!important;"><i class="fa fa-phone"></i> {{ $supportPhone }}</p>@endif
                    <p style="color:#aaa!important;"><i class="fa fa-map-marker"></i> Tanzania</p>
                </div>
            </div>
        </div>
    </div>
    <div class="copywrite-area">
        <div class="container">
            <div class="copywrite-content d-flex flex-wrap justify-content-between align-items-center">
                <a href="{{ route('landing.index') }}" class="mauzo-logo-text" style="font-size:1.1rem;"><i class="fa fa-shopping-cart"></i> {{ $platformName }}</a>
                <p class="copywrite-text mb-0">&copy; {{ date('Y') }} {{ $platformName }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<script src="{{ asset('landing/js/jquery/jquery-2.2.4.min.js') }}"></script>
<script src="{{ asset('landing/js/bootstrap/popper.min.js') }}"></script>
<script src="{{ asset('landing/js/bootstrap/bootstrap.min.js') }}"></script>
<script src="{{ asset('landing/js/plugins/plugins.js') }}"></script>
<script src="{{ asset('landing/js/active.js') }}"></script>
@stack('scripts')
</body>
</html>
