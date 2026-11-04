@php
  $openNow = $website->sectionEnabled('open_badge') ? $website->isOpenNow() : null;
  $brandParts = preg_split('/\s+/', trim((string) $business->name), 2);
  $brandFirst = $brandParts[0] ?? $business->name;
  $brandRest = $brandParts[1] ?? '';
@endphp
<header id="header" class="header d-flex align-items-center fixed-top">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between site-header-bar">

    <a href="{{ $website->publicUrl('home') }}" class="logo d-flex align-items-center me-auto me-lg-0">
      @if($business->logoUrl())
        <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}">
      @endif
      <div class="d-flex flex-column">
        <div class="d-flex align-items-baseline">
          <h1 class="sitename mb-0">{{ $business->name }}</h1><span>.</span>
        </div>
        @if($website->tagline)
          <span class="tagline d-none d-md-block">{{ $website->tagline }}</span>
        @endif
      </div>
    </a>

    <nav id="navmenu" class="navmenu site-navmenu">
      <div class="site-mobile-drawer">
        <div class="site-mobile-drawer__top">
          <a href="{{ $website->publicUrl('home') }}" class="site-mobile-drawer__brand">
            <span class="site-mobile-drawer__name">
              <span class="site-mobile-drawer__name-first">{{ $brandFirst }}</span>@if($brandRest !== '')<span class="site-mobile-drawer__name-rest">{{ $brandRest }}</span>@endif
            </span>
          </a>
          <button type="button" class="site-mobile-drawer__close" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <ul>
          @foreach($nav as $item)
            <li>
              <a href="{{ $item['url'] }}" @if(!empty($item['active'])) class="active" @endif>{{ $item['label'] }}</a>
            </li>
          @endforeach
        </ul>

        <div class="site-mobile-drawer__contact">
          @if(filled($business->email))
            <a href="mailto:{{ $business->email }}" class="site-mobile-drawer__contact-row">
              <i class="bi bi-envelope-fill"></i>
              <span>{{ $business->email }}</span>
            </a>
          @endif
          @if(filled($business->phone))
            <a href="tel:{{ preg_replace('/\s+/', '', $business->phone) }}" class="site-mobile-drawer__contact-row site-mobile-drawer__contact-row--phone">
              <i class="bi bi-telephone-outbound-fill"></i>
              <span>
                <small>{{ __('public_site.contact.inquiry') }}</small>
                <strong>{{ $business->phone }}</strong>
              </span>
            </a>
          @endif
        </div>
      </div>
    </nav>

    <div class="header-actions d-flex align-items-center gap-2 ms-2">
      @if($openNow !== null)
        <span class="site-open-badge {{ $openNow ? 'is-open' : 'is-closed' }} d-none d-md-inline-flex">
          <span class="site-open-badge__dot"></span>
          {{ $openNow ? __('public_site.status.open_now') : __('public_site.status.closed') }}
        </span>
      @endif
      @include('public-site.partials.site-controls')
      @if($whatsappDigits ?? false)
        <a class="d-none d-lg-inline text-white small fw-semibold" href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener">
          <i class="bi bi-whatsapp"></i> WhatsApp
        </a>
      @elseif($business->phone)
        <a class="d-none d-lg-inline text-white small fw-semibold" href="tel:{{ $business->phone }}">{{ $business->phone }}</a>
      @endif
      @if($website->sectionEnabled('order'))
        <a class="btn-getstarted d-none d-md-inline-block" href="{{ $website->publicUrl('order') }}">{{ __('public_site.actions.order_now') }}</a>
      @endif

      {{-- Mobile toggle stays on the RIGHT; drawer opens from the LEFT --}}
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
