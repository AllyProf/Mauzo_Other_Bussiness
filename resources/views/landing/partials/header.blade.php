<header id="header" class="header d-flex align-items-center @yield('header_class', 'fixed-top')">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

    <a href="{{ route('landing.index') }}" class="logo d-flex align-items-center me-auto me-lg-0">
      <h1 class="sitename">{{ $platformName ?? config('app.name') }}</h1><span>.</span>
    </a>

    <nav id="navmenu" class="navmenu">
      <ul>
        <li><a href="{{ route('landing.index') }}#hero" @if(request()->routeIs('landing.index')) class="active" @endif>Home</a></li>
        <li><a href="{{ route('landing.index') }}#about">About</a></li>
        <li><a href="{{ route('landing.index') }}#features">Features</a></li>
        <li><a href="{{ route('landing.index') }}#services">Modules</a></li>
        <li><a href="{{ route('landing.index') }}#pricing">Pricing</a></li>
        <li><a href="{{ route('landing.index') }}#contact">Contact</a></li>
      </ul>
      <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
    </nav>

    <div class="d-flex align-items-center gap-2 ms-2">
      <a class="d-none d-md-inline text-white small fw-semibold" href="{{ route('login') }}">Sign In</a>
      @if(request()->routeIs('register.business'))
        <span class="btn-getstarted opacity-75 pe-none">Register</span>
      @elseif($registrationOpen ?? true)
        <a class="btn-getstarted" href="{{ route('register.business') }}">Get Started</a>
      @else
        <a class="btn-getstarted" href="{{ route('landing.index') }}#contact">Request Demo</a>
      @endif
    </div>

  </div>
</header>
