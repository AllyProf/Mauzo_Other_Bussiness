<header id="header" class="header d-flex align-items-center fixed-top">
  <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

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

    <nav id="navmenu" class="navmenu">
      <ul>
        @foreach($nav as $item)
          <li>
            <a href="{{ $item['url'] }}" @if(!empty($item['active'])) class="active" @endif>{{ $item['label'] }}</a>
          </li>
        @endforeach
      </ul>
      <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
    </nav>

    <div class="header-actions d-flex align-items-center gap-2 ms-2">
      @if($whatsappDigits ?? false)
        <a class="d-none d-md-inline text-white small fw-semibold" href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener">
          <i class="bi bi-whatsapp"></i> WhatsApp
        </a>
      @elseif($business->phone)
        <a class="d-none d-md-inline text-white small fw-semibold" href="tel:{{ $business->phone }}">{{ $business->phone }}</a>
      @endif
      <a class="btn-getstarted d-none d-md-inline-block" href="{{ $website->publicUrl('order') }}">Order now</a>
    </div>

  </div>
</header>
