<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>@yield('title', $platformName ?? config('app.name'))</title>
  <meta name="description" content="@yield('meta_description', 'Modern point of sale, inventory, and business management for Tanzanian shops and enterprises.')">
  <meta name="keywords" content="POS, inventory, Tanzania, retail, Mauzo Link, spare parts, shop management">

  <link href="{{ asset('mauzo_link.png') }}" rel="icon">
  <link href="{{ asset('gp-assets/img/apple-touch-icon.png') }}" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;500;600;700&family=Raleway:wght@600;700;800&display=swap" rel="stylesheet">

  <link href="{{ asset('gp-assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/main.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/css/mauzo-brand.css') }}" rel="stylesheet">
  @stack('styles')
</head>

<body class="@yield('body_class', 'index-page')">

  @include('landing.partials.header')

  @if(session('lead_success'))
    <div class="alert alert-success alert-dismissible fade show landing-alert shadow" role="alert">
      <i class="bi bi-check-circle me-1"></i> {{ session('lead_success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show landing-alert shadow" role="alert">
      <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <main class="main">
    @yield('content')
  </main>

  @include('landing.partials.footer')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <script src="{{ asset('gp-assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/isotope-layout/isotope.pkgd.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/purecounter/purecounter_vanilla.js') }}"></script>
  <script src="{{ asset('gp-assets/js/main.js') }}"></script>
  @stack('scripts')
</body>
</html>
