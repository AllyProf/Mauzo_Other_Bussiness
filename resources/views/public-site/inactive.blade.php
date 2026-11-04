<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-site-theme="dark">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta name="robots" content="noindex,nofollow">
  <title>{{ __('public_site.inactive.title', ['name' => $business->name]) }}</title>
  <meta name="description" content="{{ __('public_site.inactive.message') }}">
  @include('partials.favicon')
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@600;700;800&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
  <link href="{{ asset('gp-assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <style>
    :root {
      --accent-color: {{ $website->primary_color ?? '#940000' }};
    }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Poppins', sans-serif;
      color: #fff;
      background:
        radial-gradient(ellipse 80% 60% at 50% 0%, color-mix(in srgb, var(--accent-color) 35%, transparent), transparent 70%),
        linear-gradient(165deg, #0c0b09 0%, #1a1210 50%, #0c0b09 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    html[data-site-theme="light"] body {
      color: #1a1814;
      background:
        radial-gradient(ellipse 80% 60% at 50% 0%, color-mix(in srgb, var(--accent-color) 18%, transparent), transparent 70%),
        linear-gradient(165deg, #f7f5f2 0%, #efeae4 50%, #f7f5f2 100%);
    }
    html[data-site-theme="light"] .inactive-card__message { color: rgba(26,24,20,.68); }
    html[data-site-theme="light"] .inactive-card__badge {
      background: rgba(26,24,20,.06);
      border-color: rgba(26,24,20,.12);
    }
    html[data-site-theme="light"] .inactive-card__phone { color: #1a1814; }
    .site-controls { display: inline-flex; align-items: center; gap: 8px; }
    .site-lang-switcher {
      display: inline-flex; align-items: center; gap: 3px; padding: 3px;
      border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
    }
    html[data-site-theme="light"] .site-lang-switcher {
      background: rgba(26,24,20,.06); border-color: rgba(26,24,20,.12);
    }
    .site-lang-switcher__form { margin: 0; }
    .site-lang-switcher__btn {
      display: inline-flex; align-items: center; gap: 4px; border: none; background: transparent;
      color: rgba(255,255,255,.88); font-size: 11px; font-weight: 700; letter-spacing: .04em;
      padding: 5px 8px; border-radius: 999px; cursor: pointer; line-height: 1;
    }
    html[data-site-theme="light"] .site-lang-switcher__btn { color: rgba(26,24,20,.75); }
    .site-lang-switcher__btn.is-active {
      background: #fff; color: var(--accent-color, #940000); box-shadow: 0 2px 8px rgba(0,0,0,.15);
    }
    .site-theme-toggle {
      width: 36px; height: 36px; border-radius: 50%; border: 1px solid rgba(255,255,255,.22);
      background: rgba(255,255,255,.12); color: #fff; display: inline-flex; align-items: center;
      justify-content: center; cursor: pointer;
    }
    html[data-site-theme="light"] .site-theme-toggle {
      border-color: rgba(26,24,20,.15); background: rgba(26,24,20,.06); color: #1a1814;
    }
    .inactive-card {
      width: min(520px, 100%);
      text-align: center;
    }
    .inactive-card__logo {
      max-height: 64px;
      width: auto;
      margin: 0 auto 1.25rem;
      display: block;
    }
    .inactive-card__name {
      font-family: 'Raleway', sans-serif;
      font-weight: 800;
      font-size: clamp(1.6rem, 4vw, 2.1rem);
      margin: 0 0 .35rem;
      letter-spacing: -0.02em;
    }
    .inactive-card__badge {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin: 1rem 0 1.25rem;
      padding: .45rem .9rem;
      border-radius: 999px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.18);
      font-size: .85rem;
      font-weight: 600;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .inactive-card__badge i { color: #f0ad4e; }
    .inactive-card__title {
      font-family: 'Raleway', sans-serif;
      font-weight: 700;
      font-size: 1.35rem;
      margin: 0 0 .65rem;
    }
    .inactive-card__message {
      color: rgba(255,255,255,.72);
      font-size: 1rem;
      line-height: 1.55;
      margin: 0 0 1.5rem;
    }
    .inactive-card__phone {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      border-bottom: 1px solid color-mix(in srgb, var(--accent-color) 70%, #fff);
      padding-bottom: 2px;
    }
    .inactive-card__phone:hover { color: #fff; opacity: .9; }
    .inactive-card__controls {
      margin-top: 2rem;
      display: flex;
      justify-content: center;
    }
  </style>
</head>
<body>
  <div class="inactive-card">
    @if($business->logoUrl())
      <img class="inactive-card__logo" src="{{ $business->logoUrl() }}" alt="{{ $business->name }}">
    @endif
    <h1 class="inactive-card__name">{{ $business->name }}</h1>
    <div class="inactive-card__badge">
      <i class="bi bi-shop-window"></i>
      {{ __('public_site.inactive.badge') }}
    </div>
    <h2 class="inactive-card__title">{{ __('public_site.inactive.heading') }}</h2>
    <p class="inactive-card__message">{{ __('public_site.inactive.message') }}</p>
    @if($business->phone)
      <a class="inactive-card__phone" href="tel:{{ preg_replace('/\s+/', '', $business->phone) }}">
        <i class="bi bi-telephone"></i> {{ $business->phone }}
      </a>
    @endif
    <div class="inactive-card__controls">
      @include('public-site.partials.site-controls')
    </div>
  </div>
  <script>
    (function () {
      try {
        var t = localStorage.getItem('business-site-theme');
        if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-site-theme', t);
      } catch (e) {}
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
  </script>
</body>
</html>
