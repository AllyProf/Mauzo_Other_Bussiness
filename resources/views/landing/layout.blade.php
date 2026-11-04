<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  @hasSection('seo_head')
    @yield('seo_head')
  @else
    @include('landing.partials.seo-head')
  @endif

  @include('partials.favicon')

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;500;600;700&family=Raleway:wght@600;700;800&display=swap" rel="stylesheet">

  <link href="{{ asset('gp-assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/main.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/mauzo-brand.css') }}?v={{ @filemtime(public_path('gp-assets/css/mauzo-brand.css')) }}" rel="stylesheet">
  <style>
    /* Mobile: toggle RIGHT, drawer opens LEFT (same as business public site) */
    .site-mobile-drawer__top,
    .site-mobile-drawer__contact,
    .site-mobile-drawer__close { display: none; }
    .site-menu-toggle {
      appearance: none;
      -webkit-appearance: none;
      padding: 0;
      cursor: pointer;
      flex-shrink: 0;
    }
    .site-menu-toggle__bars {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 6px;
      width: 18px;
    }
    .site-menu-toggle__bars span {
      display: block;
      height: 2px;
      width: 100%;
      border-radius: 2px;
      background: currentColor;
    }
    .site-menu-toggle__bars span:last-child { width: 70%; }

    @media (max-width: 1199px) {
      .landing-site .site-header-bar { flex-wrap: nowrap; }
      .landing-site .logo { order: 0; }
      .landing-site .navmenu { order: 1; width: 0; height: 0; margin: 0; padding: 0; overflow: visible; }
      .landing-site .header-actions { order: 2; margin-left: auto !important; gap: 8px; }

      .landing-site .site-menu-toggle.mobile-nav-toggle {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 1.5px solid var(--accent-color, #940000);
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        margin: 0 0 0 4px;
        color: var(--accent-color, #940000);
        background: transparent;
        z-index: 10001;
        font-size: 0;
        line-height: 0;
        box-shadow: none;
      }
      .landing-site.mobile-nav-active .site-menu-toggle.mobile-nav-toggle {
        position: fixed !important;
        top: 14px !important;
        right: 14px !important;
        opacity: 1;
        pointer-events: auto;
        color: var(--accent-color, #940000);
        border-color: var(--accent-color, #940000);
        background: #fff;
        z-index: 10002;
      }

      .landing-site.mobile-nav-active .navmenu {
        position: fixed;
        inset: 0;
        width: auto;
        height: auto;
        background: rgba(0, 0, 0, .45);
        z-index: 9997;
        transition: background .25s ease;
      }

      .landing-site .navmenu .site-mobile-drawer {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: min(330px, 84vw);
        height: 100dvh;
        margin: 0;
        padding: 0;
        border-radius: 0;
        background: #ffffff;
        box-shadow: 10px 0 36px rgba(0,0,0,.22);
        display: flex !important;
        flex-direction: column;
        z-index: 9998;
        transform: translateX(-105%);
        transition: transform .32s cubic-bezier(.22,.61,.36,1);
        overflow: hidden;
      }
      .landing-site.mobile-nav-active .site-mobile-drawer { transform: translateX(0); }

      .landing-site .navmenu ul {
        position: static !important;
        inset: auto !important;
        display: block !important;
        flex: 1 1 auto;
        overflow-y: auto;
        background: transparent !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 10px 0 18px !important;
        margin: 0 !important;
        list-style: none;
        visibility: visible;
        opacity: 1;
        transform: none;
      }
      .landing-site .navmenu li { margin: 0; }

      .landing-site .site-mobile-drawer__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 26px 20px 20px;
      }
      .landing-site .site-mobile-drawer__brand {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        min-width: 0;
      }
      .landing-site .site-mobile-drawer__name {
        font-family: 'Raleway', sans-serif;
        font-weight: 800;
        font-size: 1.15rem;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .landing-site .site-mobile-drawer__name-first { color: #1a1814; }
      .landing-site .site-mobile-drawer__name-rest {
        color: var(--accent-color, #940000);
        margin-left: .15em;
      }
      .landing-site .site-mobile-drawer__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1px solid rgba(26,24,20,.16);
        background: #fff;
        color: #333;
        flex-shrink: 0;
        cursor: pointer;
      }
      .landing-site .site-mobile-drawer__close i { font-size: 1rem; line-height: 1; }

      .landing-site .navmenu a,
      .landing-site .navmenu a:focus {
        color: #222 !important;
        background: transparent !important;
        padding: 15px 24px !important;
        font-size: .92rem !important;
        font-weight: 700 !important;
        letter-spacing: .08em;
        text-transform: uppercase;
        border: 0 !important;
        justify-content: flex-start !important;
      }
      .landing-site .navmenu a:hover,
      .landing-site .navmenu .active,
      .landing-site .navmenu .active:focus {
        color: var(--accent-color, #940000) !important;
        background: transparent !important;
      }

      .landing-site .site-mobile-drawer__contact {
        display: block;
        margin-top: auto;
        padding: 20px 24px 32px;
        border-top: 1px solid rgba(26,24,20,.1);
      }
      .landing-site .site-mobile-drawer__contact-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        color: #222 !important;
        text-decoration: none;
        padding: 10px 0 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        font-weight: 500 !important;
        font-size: .92rem !important;
      }
      .landing-site .site-mobile-drawer__contact-row i {
        color: var(--accent-color, #940000);
        font-size: 1.05rem;
        margin-top: 3px;
        width: auto !important;
        height: auto !important;
        background: transparent !important;
        border-radius: 0 !important;
        margin-left: 0 !important;
      }
      .landing-site .site-mobile-drawer__contact-row--phone small {
        display: block;
        color: #444;
        font-size: .78rem;
        font-weight: 500;
        margin-bottom: 2px;
      }
      .landing-site .site-mobile-drawer__contact-row--phone strong {
        display: block;
        color: var(--accent-color, #940000);
        font-size: 1.05rem;
        font-weight: 700;
      }
    }
    @media (min-width: 1200px) {
      .landing-site .site-mobile-drawer { display: contents; }
      .landing-site .site-mobile-drawer__top,
      .landing-site .site-mobile-drawer__contact { display: none !important; }
      .landing-site .site-menu-toggle { display: none !important; }
    }
  </style>
  @stack('styles')
</head>

<body class="@yield('body_class', 'index-page') landing-site">

  @include('landing.partials.header')

  @if(session('lead_success'))
    <div class="alert alert-success alert-dismissible fade show landing-alert shadow" role="alert">
      <i class="bi bi-check-circle me-1"></i> {{ session('lead_success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('landing.close') }}"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show landing-alert shadow" role="alert">
      <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('landing.close') }}"></button>
    </div>
  @endif

  <main class="main">
    @yield('content')
  </main>

  @include('landing.partials.footer')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader" role="progressbar" aria-busy="true">
    <div class="preloader-bar" aria-hidden="true"></div>
  </div>

  <script src="{{ asset('gp-assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/isotope-layout/isotope.pkgd.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/purecounter/purecounter_vanilla.js') }}"></script>
  <script src="{{ asset('gp-assets/js/main.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var closeBtn = document.querySelector('.site-mobile-drawer__close');
      var toggleBtn = document.querySelector('.mobile-nav-toggle');
      var navmenu = document.getElementById('navmenu');
      function closeMobileNav() {
        if (!document.body.classList.contains('mobile-nav-active')) return;
        document.body.classList.remove('mobile-nav-active');
        if (toggleBtn) toggleBtn.classList.remove('bi-x');
      }
      if (closeBtn) closeBtn.addEventListener('click', closeMobileNav);
      if (navmenu) {
        navmenu.addEventListener('click', function (e) {
          if (e.target === navmenu) closeMobileNav();
        });
      }
    });
  </script>
  @stack('scripts')
</body>
</html>
