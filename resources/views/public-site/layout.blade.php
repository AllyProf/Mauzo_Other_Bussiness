<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-site-theme="dark">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  @php
    $seoTitle = trim($__env->yieldContent('title', $business->name));
    $seoDescription = trim((string) ($website->tagline ?: (($content['hero_subtitle'] ?? null) ?: $business->name)));
    $seoUrl = $website->publicUrl();
    $seoImage = $website->heroImageUrl();
    if (str_starts_with($seoImage, '/')) {
        $seoImage = url($seoImage);
    }
  @endphp
  <title>{{ $seoTitle }}</title>
  <meta name="description" content="{{ $seoDescription }}">
  <link rel="canonical" href="{{ $seoUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $business->name }}">
  <meta property="og:title" content="{{ $seoTitle }}">
  <meta property="og:description" content="{{ $seoDescription }}">
  <meta property="og:url" content="{{ $seoUrl }}">
  <meta property="og:image" content="{{ $seoImage }}">
  <meta property="og:locale" content="{{ app()->getLocale() === 'sw' ? 'sw_TZ' : 'en_TZ' }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seoTitle }}">
  <meta name="twitter:description" content="{{ $seoDescription }}">
  <meta name="twitter:image" content="{{ $seoImage }}">
  <script>
    (function () {
      try {
        var t = localStorage.getItem('business-site-theme');
        if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-site-theme', t);
      } catch (e) {}
    })();
  </script>

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
      --site-panel-bg: #0c0b09;
      --site-panel-bg-alt: #11100e;
      --site-panel-text: #fff;
      --site-panel-muted: rgba(255,255,255,.7);
      --site-panel-soft: rgba(255,255,255,.88);
    }
    html[data-site-theme="light"] {
      --site-panel-bg: #ffffff;
      --site-panel-bg-alt: #f4f4f2;
      --site-panel-text: #1a1814;
      --site-panel-muted: rgba(26,24,20,.68);
      --site-panel-soft: rgba(26,24,20,.88);
    }
    .business-site .header .logo img { max-height: 42px; width: auto; margin-right: 8px; }
    .business-site .header .logo .sitename { font-size: 1.35rem; margin: 0; }
    .business-site .header .logo .tagline {
      display: block; font-size: .72rem; font-weight: 500; color: rgba(255,255,255,.65);
      letter-spacing: 0; font-family: var(--default-font);
    }

    /* Header controls: language + theme */
    .site-controls {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .site-lang-switcher {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 3px;
      border-radius: 999px;
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.2);
    }
    .site-lang-switcher__form { margin: 0; }
    .site-lang-switcher__btn {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      border: none;
      background: transparent;
      color: rgba(255,255,255,.88);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .04em;
      padding: 5px 8px;
      border-radius: 999px;
      cursor: pointer;
      line-height: 1;
      transition: background .15s, color .15s;
    }
    .site-lang-switcher__btn:hover { background: rgba(255,255,255,.15); color: #fff; }
    .site-lang-switcher__btn.is-active {
      background: #fff;
      color: var(--accent-color, #940000);
      box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .site-lang-switcher .locale-flag { box-shadow: none; }
    .site-theme-toggle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,.22);
      background: rgba(255,255,255,.12);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background .15s, transform .15s;
    }
    .site-theme-toggle:hover { background: rgba(255,255,255,.22); transform: scale(1.05); }
    .site-theme-toggle i { font-size: 1rem; line-height: 1; }

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

    /* Menu board */
    .menu-section {
      background: var(--site-panel-bg);
      color: var(--site-panel-text);
      padding-top: 70px;
      padding-bottom: 80px;
      transition: background-color .25s ease, color .25s ease;
    }
    .menu-section .section-title--light h2,
    .menu-section .section-title h2 { color: var(--site-panel-text); }
    .menu-section .section-title--light p,
    .menu-section .section-title p { color: var(--site-panel-muted); }
    .menu-section .section-title h2::after { background: var(--accent-color); }

    .site-page-body { padding-top: 60px; padding-bottom: 70px; }
    .site-highlight-card {
      background: #f8f9fa;
      border-radius: 8px;
    }
    #hero, #products, #about, #contact, #order, #gallery, #promo, #share {
      scroll-margin-top: 80px;
    }

    /* Light theme overrides for dark-styled blocks defined in page styles */
    html[data-site-theme="light"] .feature-split-section {
      background: var(--site-panel-bg) !important;
      color: var(--site-panel-text) !important;
    }
    html[data-site-theme="light"] .feature-split-section--alt {
      background: var(--site-panel-bg-alt) !important;
    }
    html[data-site-theme="light"] .feature-split__title,
    html[data-site-theme="light"] .feature-split__list a {
      color: var(--site-panel-text) !important;
    }
    html[data-site-theme="light"] .feature-split__lead {
      color: var(--site-panel-muted) !important;
    }
    html[data-site-theme="light"] .feature-split__list li {
      color: var(--site-panel-soft) !important;
    }
    html[data-site-theme="light"] .feature-split__media {
      background: #eee;
      box-shadow: 0 10px 28px rgba(0,0,0,.08);
    }
    html[data-site-theme="light"] .feature-split__map-link {
      background: rgba(255,255,255,.94);
      color: #1a1814 !important;
      border-color: rgba(0,0,0,.08);
    }
    html[data-site-theme="light"] .cta-btn {
      color: var(--site-panel-text);
      border-color: rgba(26,24,20,.25);
    }
    html[data-site-theme="light"] .cta-btn:hover {
      color: #fff;
      background: var(--accent-color);
      border-color: var(--accent-color);
    }
    html[data-site-theme="light"] .site-footer__wave path:first-child {
      fill: var(--site-panel-bg-alt);
    }

    .site-open-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .28rem .7rem;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .03em;
      text-transform: uppercase;
      border: 1px solid rgba(255,255,255,.2);
      background: rgba(255,255,255,.1);
      color: #fff;
      white-space: nowrap;
    }
    .site-open-badge__dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: currentColor;
      box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 25%, transparent);
    }
    .site-open-badge.is-open { color: #7dffa3; border-color: rgba(125,255,163,.35); }
    .site-open-badge.is-closed { color: #ffb4b4; border-color: rgba(255,180,180,.35); }

    .site-share-btn {
      width: 36px; height: 36px; border-radius: 50%;
      border: 1px solid rgba(255,255,255,.22);
      background: rgba(255,255,255,.12);
      color: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer;
    }
    .site-share-btn:hover { background: rgba(255,255,255,.22); }
    .site-share-btn.is-copied { background: #198754; border-color: #198754; }

    .site-wa-float {
      position: fixed;
      right: 18px;
      bottom: 88px;
      z-index: 995;
      width: 56px; height: 56px;
      border-radius: 50%;
      background: #25d366;
      color: #fff !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      box-shadow: 0 10px 28px rgba(37, 211, 102, .45);
      transition: transform .18s ease;
    }
    .site-wa-float:hover { transform: translateY(-2px) scale(1.05); color: #fff !important; }

    @media (max-width: 767px) {
      .site-page-hero { min-height: 260px; padding: 120px 0 48px; }
      .business-hero { min-height: 380px; }
      .site-lang-switcher__btn span { display: none; }
      .header-actions .btn-getstarted { display: none !important; }
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
  @include('public-site.partials.whatsapp-float')

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <script src="{{ asset('gp-assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('gp-assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('gp-assets/js/main.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof AOS !== 'undefined') AOS.init({ duration: 600, once: true });
    });

    (function () {
      var toggle = document.getElementById('siteThemeToggle');
      if (!toggle) return;
      var root = document.documentElement;
      var labels = {
        toLight: @json(__('public_site.theme.to_light')),
        toDark: @json(__('public_site.theme.to_dark'))
      };
      var iconDark = toggle.querySelector('[data-theme-icon-dark]');
      var iconLight = toggle.querySelector('[data-theme-icon-light]');

      function sync() {
        var light = root.getAttribute('data-site-theme') === 'light';
        var label = light ? labels.toDark : labels.toLight;
        toggle.setAttribute('title', label);
        toggle.setAttribute('aria-label', label);
        if (iconDark) iconDark.classList.toggle('d-none', light);
        if (iconLight) iconLight.classList.toggle('d-none', !light);
      }

      toggle.addEventListener('click', function () {
        var next = root.getAttribute('data-site-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-site-theme', next);
        try { localStorage.setItem('business-site-theme', next); } catch (e) {}
        sync();
      });

      sync();
    })();

    (function () {
      document.querySelectorAll('[data-site-share]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          var url = btn.getAttribute('data-share-url') || window.location.href;
          var title = btn.getAttribute('data-share-title') || document.title;
          var text = btn.getAttribute('data-share-text') || title;
          var copied = btn.getAttribute('data-copied-label') || 'Copied';

          try {
            if (navigator.share) {
              await navigator.share({ title: title, text: text, url: url });
              return;
            }
          } catch (e) {
            if (e && e.name === 'AbortError') return;
          }

          try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              await navigator.clipboard.writeText(url);
            } else {
              var ta = document.createElement('textarea');
              ta.value = url;
              document.body.appendChild(ta);
              ta.select();
              document.execCommand('copy');
              document.body.removeChild(ta);
            }
            btn.classList.add('is-copied');
            var prev = btn.getAttribute('title');
            btn.setAttribute('title', copied);
            setTimeout(function () {
              btn.classList.remove('is-copied');
              if (prev) btn.setAttribute('title', prev);
            }, 1600);
          } catch (err) {}
        });
      });
    })();
  </script>
  @stack('scripts')
</body>
</html>
