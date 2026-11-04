@php
  $platformLabel = trim((string) ($platformName ?? config('app.name')));
  $brandParts = preg_split('/\s+/', $platformLabel, 2);
  $brandFirst = $brandParts[0] ?? $platformLabel;
  $brandRest = $brandParts[1] ?? '';
  $drawerEmail = $supportEmail ?? '';
  $drawerPhone = $supportPhone ?? '';
@endphp
<header id="header" class="header d-flex align-items-center @yield('header_class', 'fixed-top')">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between site-header-bar">

    <a href="{{ route('landing.index') }}" class="logo d-flex align-items-center me-auto me-lg-0">
      <span class="sitename">{{ $platformLabel }}</span><span class="logo-dot">.</span>
    </a>

    <nav id="navmenu" class="navmenu site-navmenu">
      <div class="site-mobile-drawer">
        <div class="site-mobile-drawer__top">
          <a href="{{ route('landing.index') }}" class="site-mobile-drawer__brand">
            <span class="site-mobile-drawer__name">
              <span class="site-mobile-drawer__name-first">{{ $brandFirst }}</span>@if($brandRest !== '')<span class="site-mobile-drawer__name-rest">{{ $brandRest }}</span>@endif
            </span>
          </a>
          <button type="button" class="site-mobile-drawer__close" aria-label="{{ __('landing.close') }}">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <ul>
          <li><a href="{{ route('landing.index') }}#hero" @if(request()->routeIs('landing.index')) class="active" @endif>{{ __('landing.nav.home') }}</a></li>
          <li><a href="{{ route('landing.index') }}#about">{{ __('landing.nav.about') }}</a></li>
          <li><a href="{{ route('landing.index') }}#mobile-app">{{ __('landing.nav.mobile_app') }}</a></li>
          <li><a href="{{ route('landing.index') }}#pricing">{{ __('landing.nav.pricing') }}</a></li>
          <li><a href="{{ route('landing.index') }}#contact">{{ __('landing.nav.contact') }}</a></li>
          <li class="d-xl-none"><a href="{{ route('login') }}">{{ __('landing.nav.login') }}</a></li>
          @if($registrationOpen ?? true)
            <li class="d-xl-none"><a href="{{ route('register.business') }}">{{ __('landing.nav.get_started') }}</a></li>
          @else
            <li class="d-xl-none"><a href="{{ route('landing.index') }}#contact">{{ __('landing.nav.request_demo') }}</a></li>
          @endif
        </ul>

        <div class="site-mobile-drawer__contact">
          @if(filled($drawerEmail))
            <a href="mailto:{{ $drawerEmail }}" class="site-mobile-drawer__contact-row">
              <i class="bi bi-envelope-fill"></i>
              <span>{{ $drawerEmail }}</span>
            </a>
          @endif
          @if(filled($drawerPhone))
            <a href="tel:{{ preg_replace('/\s+/', '', $drawerPhone) }}" class="site-mobile-drawer__contact-row site-mobile-drawer__contact-row--phone">
              <i class="bi bi-telephone-outbound-fill"></i>
              <span>
                <small>{{ __('landing.contact.inquiry') }}</small>
                <strong>{{ $drawerPhone }}</strong>
              </span>
            </a>
          @endif
        </div>
      </div>
    </nav>

    <div class="header-actions d-flex align-items-center gap-2 ms-2">
      @include('landing.partials.language-switcher')
      <a class="d-none d-md-inline text-white small fw-semibold" href="{{ route('login') }}">{{ __('landing.nav.sign_in') }}</a>
      @if(request()->routeIs('register.business'))
        <span class="btn-getstarted opacity-75 pe-none d-none d-md-inline-block">{{ __('landing.nav.register') }}</span>
      @elseif($registrationOpen ?? true)
        <a class="btn-getstarted d-none d-md-inline-block" href="{{ route('register.business') }}">{{ __('landing.nav.get_started') }}</a>
      @else
        <a class="btn-getstarted d-none d-md-inline-block" href="{{ route('landing.index') }}#contact">{{ __('landing.nav.request_demo') }}</a>
      @endif

      <button type="button"
              class="mobile-nav-toggle site-menu-toggle d-xl-none"
              aria-label="Open menu"
              aria-controls="navmenu">
        <span class="site-menu-toggle__bars" aria-hidden="true">
          <span></span>
          <span></span>
        </span>
      </button>
    </div>

  </div>
</header>
