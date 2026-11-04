<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>@yield('title', $business->name)</title>
  <meta name="description" content="{{ $website->tagline ?? $business->name }}">

  @include('partials.favicon')
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;500;600;700&family=Raleway:wght@600;700;800&display=swap" rel="stylesheet">

  <link href="{{ asset('gp-assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/main.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/mauzo-brand.css') }}?v={{ @filemtime(public_path('gp-assets/css/mauzo-brand.css')) }}" rel="stylesheet">
  <style>
    :root {
      --accent-color: {{ $website->primary_color ?? '#940000' }};
      --nav-hover-color: color-mix(in srgb, var(--accent-color) 35%, #fff);
      --nav-dropdown-hover-color: var(--accent-color);
    }
    .business-site .header .logo img { max-height: 42px; width: auto; margin-right: 8px; }
    .business-site .header .logo .sitename { font-size: 1.35rem; margin: 0; }
    .business-site .header .logo .tagline {
      display: block; font-size: .72rem; font-weight: 500; color: rgba(255,255,255,.65);
      letter-spacing: 0; font-family: var(--default-font);
    }

    /* Page heroes */
    .site-page-hero {
      position: relative;
      min-height: 320px;
      display: flex;
      align-items: center;
      padding: 140px 0 72px;
      background: #111 center / cover no-repeat;
      background-image: linear-gradient(180deg, rgba(0,0,0,.72), rgba(0,0,0,.55)), var(--hero-image);
      color: #fff;
      overflow: hidden;
    }
    .site-page-hero__overlay { display: none; }
    .site-page-hero__title {
      font-size: clamp(1.85rem, 4vw, 2.75rem);
      font-weight: 800;
      margin: 0 0 .65rem;
      font-family: 'Raleway', sans-serif;
    }
    .site-page-hero__sub {
      font-size: 1.05rem;
      color: rgba(255,255,255,.82);
      max-width: 36rem;
      margin-left: auto;
      margin-right: auto;
    }

    /* Dark restaurant-style menu board */
    .menu-section {
      background: #0c0b09;
      color: #fff;
      padding-top: 70px;
      padding-bottom: 80px;
    }
    .menu-section .section-title--light h2,
    .menu-section .section-title h2 { color: #fff; }
    .menu-section .section-title--light p,
    .menu-section .section-title p { color: rgba(255,255,255,.7); }
    .menu-section .section-title h2::after { background: var(--accent-color); }

    .site-page-body { padding-top: 60px; padding-bottom: 70px; }
    .site-highlight-card {
      background: #f8f9fa;
      border-radius: 8px;
    }
    #hero, #products, #about, #contact, #order {
      scroll-margin-top: 80px;
    }

    @media (max-width: 767px) {
      .site-page-hero { min-height: 260px; padding: 120px 0 48px; }
      .business-hero { min-height: 380px; }
    }
  </style>
  @stack('styles')
</head>

<body class="business-site index-page @yield('body_class')">

  @include('public-site.partials.header')

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show landing-alert shadow mt-5 pt-4" role="alert" style="margin-top: 90px !important; max-width: 900px; margin-left: auto; margin-right: auto;">
      <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <main class="main">
    @yield('content')
  </main>

  @include('public-site.partials.footer')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <script src="{{ asset('gp-assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('gp-assets/js/main.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof AOS !== 'undefined') AOS.init({ duration: 600, once: true });
    });
  </script>
  @stack('scripts')
</body>
</html>
