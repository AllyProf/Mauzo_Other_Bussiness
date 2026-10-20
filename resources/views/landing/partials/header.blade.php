<header id="header" class="header d-flex align-items-center @yield('header_class', 'fixed-top')">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

    <a href="{{ route('landing.index') }}" class="logo d-flex align-items-center me-auto me-lg-0">
      <span class="sitename">{{ $platformName ?? config('app.name') }}</span><span class="logo-dot">.</span>
    </a>

    <nav id="navmenu" class="navmenu">
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
      <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
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
    </div>

  </div>
</header>
