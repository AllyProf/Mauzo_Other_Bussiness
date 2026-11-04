@extends('public-site.layout')

@section('title', ($content['hero_title'] ?? $business->name).' — '.$business->name)

@section('content')
@php
  $products = $sections['products'] ?? [];
  $contact = $sections['contact'] ?? [];
  $order = $sections['order'] ?? [];

  $cta = trim((string) ($content['hero_button_url'] ?? ''));
  if ($cta === '' || $cta === '/order' || $cta === 'order') {
      $cta = $website->publicUrl('order');
  } elseif ($cta === 'products' || $cta === '/products') {
      $cta = $website->publicUrl('products');
  } elseif (! str_starts_with($cta, 'http') && ! str_starts_with($cta, '#')) {
      $cta = $website->publicUrl(ltrim($cta, '/'));
  }

  $totalInStock = $catalog->flatten(1)->count();
  $productsCaption = trim((string) ($products['body'] ?? $content['intro_body'] ?? ''));
  if ($productsCaption === '' || str_contains(strtolower($productsCaption), 'updated live from') || str_contains(strtolower($productsCaption), 'browse our current')) {
      $productsCaption = $totalInStock > 0 ? __('public_site.products.in_stock') : __('public_site.products.visit_us');
  }

  $isBoilerplate = function (?string $text): bool {
      $t = strtolower(trim((string) $text));
      if ($t === '') {
          return true;
      }
      return str_contains($t, 'edit it in mauzo')
          || str_contains($t, 'serves customers with care')
          || str_contains($t, 'this page tells your story')
          || str_contains($t, 'updated live from');
  };

  $contactTitle = $contact['title'] ?? __('public_site.contact.title');
  $contactCaption = trim((string) ($contact['body'] ?? ''));
  if ($isBoilerplate($contactCaption) || strlen($contactCaption) > 90) {
      $contactCaption = __('public_site.contact.caption');
  }

  $orderTitle = $order['title'] ?? __('public_site.order.title');
  $orderCaption = trim((string) ($order['body'] ?? ''));
  if ($isBoilerplate($orderCaption) || strlen($orderCaption) > 90) {
      $orderCaption = __('public_site.order.caption');
  }
  $orderSteps = collect(preg_split('/\r\n|\r|\n/', (string) ($order['steps'] ?? '')))
      ->map(fn ($s) => trim(preg_replace('/^\d+[\.\)]\s*/', '', $s)))
      ->filter()
      ->values()
      ->all();
  if ($orderSteps === []) {
      $orderSteps = __('public_site.order.steps');
  }

  $heroImage = $website->heroImageUrl();
  $orderFeatureImage = null;
  foreach ([
      'gp-assets/img/order-image.jpg',
      'gp-assets/img/business-site/order-image.jpg',
      'gp-assets/img/business-site/order-steps.jpg',
      'gp-assets/img/business-site/order-photo.jpg',
  ] as $orderRel) {
      if (is_file(public_path($orderRel))) {
          $orderFeatureImage = asset($orderRel).'?v='.@filemtime(public_path($orderRel));
          break;
      }
  }
  $scanMenuImage = null;
  foreach ([
      'gp-assets/img/qr-code-animation.gif',
      'gp-assets/img/scan-menu.gif',
      'gp-assets/img/business-site/qr-code-animation.gif',
      'gp-assets/img/business-site/scan-menu.gif',
  ] as $scanRel) {
      if (is_file(public_path($scanRel))) {
          $scanMenuImage = asset($scanRel).'?v='.@filemtime(public_path($scanRel));
          break;
      }
  }
  $contactMap = $website->contactMap();
  $showMap = ! empty($contactMap['show']);
  $mapEmbedSrc = $contactMap['embed'];
  $mapOpenUrl = $contactMap['open'];

  $showPromo = $website->sectionEnabled('promo');
  $showProducts = $website->sectionEnabled('products');
  $showOrder = $website->sectionEnabled('order');
  $showGallery = $website->sectionEnabled('gallery');
  $showContact = $website->sectionEnabled('contact');
  $showShare = $website->sectionEnabled('share');

  $promoTitle = trim((string) ($content['promo_title'] ?? ''));
  if ($promoTitle === '') {
      $promoTitle = __('public_site.promo.title');
  }
  $promoItems = collect($content['promo_items'] ?? [])
      ->map(fn ($line) => trim((string) $line))
      ->filter()
      ->take(5)
      ->values()
      ->all();
  $galleryUrls = $website->galleryImageUrls();
  $deliveryNotes = trim((string) ($order['delivery_notes'] ?? ''));
  $paymentNotes = trim((string) ($order['payment_notes'] ?? ''));
  $hoursText = trim((string) ($contact['hours'] ?? ''));
@endphp

<section id="hero" class="hero section dark-background business-hero" style="background-image: url('{{ $heroImage }}');">
  <div class="business-hero__shade" aria-hidden="true"></div>
  <div class="container">
    <div class="row justify-content-center text-center" data-aos="fade-up">
      <div class="col-xl-9 col-lg-10">
        <h2 class="business-hero__title">{{ $content['hero_title'] ?? $business->name }}</h2>
        <p class="business-hero__sub lead mt-3 mb-0">{{ $content['hero_subtitle'] ?? $website->tagline ?? '' }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
          @if(!empty($content['hero_button_label']))
            <a href="{{ $cta }}" class="btn-getstarted px-4 py-2">{{ $content['hero_button_label'] }}</a>
          @endif
          @if($showProducts)
            <a href="#products" class="cta-btn px-4 py-2">{{ __('public_site.actions.view_products') }}</a>
          @endif
        </div>
      </div>
    </div>
  </div>
</section>

@if($showPromo && $promoItems !== [])
<section id="promo" class="section site-promo-section">
  <div class="container" data-aos="fade-up">
    <div class="site-promo">
      <div class="site-promo__head">
        <span class="why-eyebrow">{{ $promoTitle }}</span>
      </div>
      <div class="site-promo__grid">
        @foreach($promoItems as $promoLine)
          <div class="site-promo__card">
            <i class="bi bi-stars"></i>
            <span>{{ $promoLine }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endif

@if($showProducts)
<section id="products" class="section menu-section">
  <div class="container section-title section-title--light" data-aos="fade-up">
    <h2>{{ $products['title'] ?? ($content['intro_title'] ?? __('public_site.products.title')) }}</h2>
    <p>{{ $productsCaption }}</p>
  </div>
  <div class="container" data-aos="fade-up" data-aos-delay="100">
    @include('public-site.partials.catalog-offerings', ['catalog' => $catalog, 'business' => $business])
    @if($totalInStock > 0)
      <div class="text-center mt-5 pt-2">
        @if($showOrder)
          <a href="#order" class="btn-getstarted px-4 py-2 me-2"><i class="bi bi-cart-check"></i> {{ __('public_site.actions.order_now') }}</a>
        @endif
        @if($showContact)
          <a href="#contact" class="cta-btn px-4 py-2">{{ __('public_site.actions.contact_us') }}</a>
        @endif
      </div>
    @endif
  </div>
</section>
@endif

@if($showOrder)
<section id="order" class="section feature-split-section feature-split-section--alt">
  <div class="container" data-aos="fade-up">
    <div class="row align-items-stretch g-4 g-lg-5 order-split-row">
      <div class="col-lg-6 d-flex">
        <div class="feature-split__content w-100">
          <span class="why-eyebrow">{{ __('public_site.order.eyebrow') }}</span>
          <h2 class="feature-split__title">{{ $orderTitle }}</h2>
          <p class="feature-split__lead">{{ $orderCaption }}</p>
          <ul class="feature-split__list">
            @foreach($orderSteps as $step)
              <li><i class="bi bi-check-circle-fill"></i> <span>{{ $step }}</span></li>
            @endforeach
          </ul>
          @if($deliveryNotes !== '' || $paymentNotes !== '')
            <div class="site-order-notes">
              @if($deliveryNotes !== '')
                <div class="site-order-note">
                  <strong><i class="bi bi-truck"></i> {{ __('public_site.notes.delivery') }}</strong>
                  <p>{{ $deliveryNotes }}</p>
                </div>
              @endif
              @if($paymentNotes !== '')
                <div class="site-order-note">
                  <strong><i class="bi bi-cash-coin"></i> {{ __('public_site.notes.payment') }}</strong>
                  <p>{{ $paymentNotes }}</p>
                </div>
              @endif
            </div>
          @endif
          <div class="d-flex flex-wrap gap-2 mt-4">
            @if($whatsappDigits)
              @php $waMsg = urlencode($order['whatsapp_message'] ?? __('public_site.order.wa_message', ['name' => $business->name])); @endphp
              <a href="https://wa.me/{{ $whatsappDigits }}?text={{ $waMsg }}" target="_blank" rel="noopener" class="btn-getstarted px-4 py-2">
                <i class="bi bi-whatsapp"></i> {{ __('public_site.actions.order_whatsapp') }}
              </a>
            @endif
            @if($business->phone)
              <a href="tel:{{ $business->phone }}" class="cta-btn px-4 py-2"><i class="bi bi-telephone"></i> {{ __('public_site.actions.call_to_order') }}</a>
            @endif
            <a href="#products" class="cta-btn px-4 py-2">{{ __('public_site.actions.view_products') }}</a>
          </div>
        </div>
      </div>
      @if($orderFeatureImage)
      <div class="col-lg-6 d-flex">
        <div class="feature-split__media feature-split__media--steps w-100">
          <img src="{{ $orderFeatureImage }}" alt="{{ __('public_site.order.how_to') }}" width="800" height="800" loading="lazy" decoding="async">
        </div>
      </div>
      @endif
    </div>
  </div>
</section>
@endif

@if($showGallery && $galleryUrls !== [])
<section id="gallery" class="section site-gallery-section">
  <div class="container section-title" data-aos="fade-up">
    <span class="why-eyebrow">{{ __('public_site.gallery.eyebrow') }}</span>
    <h2>{{ __('public_site.gallery.title') }}</h2>
    <p>{{ __('public_site.gallery.caption') }}</p>
  </div>
  <div class="container" data-aos="fade-up" data-aos-delay="80">
    <div class="site-gallery-grid">
      @foreach($galleryUrls as $gUrl)
        <a href="{{ $gUrl }}" class="site-gallery-item" target="_blank" rel="noopener">
          <img src="{{ $gUrl }}" alt="{{ $business->name }}" loading="lazy" width="400" height="400">
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($showContact)
<section id="contact" class="section feature-split-section">
  <div class="container" data-aos="fade-up">
    <div class="row align-items-stretch g-4 g-lg-5">
      @if($showMap)
      <div class="col-lg-6 d-flex">
        <div class="feature-split__media feature-split__media--map w-100">
          <iframe
            title="{{ __('public_site.contact.map_title', ['name' => $business->name]) }}"
            src="{{ $mapEmbedSrc }}"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen></iframe>
          <a class="feature-split__map-link" href="{{ $mapOpenUrl }}" target="_blank" rel="noopener">
            <i class="bi bi-geo-alt-fill"></i> {{ __('public_site.actions.open_maps') }}
          </a>
        </div>
      </div>
      @endif
      <div class="{{ $showMap ? 'col-lg-6' : 'col-lg-8 mx-auto' }} d-flex">
        <div class="feature-split__content w-100">
          <span class="why-eyebrow">{{ __('public_site.contact.eyebrow') }}</span>
          <h2 class="feature-split__title">{{ $contactTitle }}</h2>
          <p class="feature-split__lead">{{ $contactCaption }}</p>
          <ul class="feature-split__list">
            @if(filled($business->address))
              <li><i class="bi bi-check-circle-fill"></i> <span>{{ $business->address }}</span></li>
            @endif
            @if(filled($business->phone))
              <li><i class="bi bi-check-circle-fill"></i> <span><a href="tel:{{ $business->phone }}">{{ $business->phone }}</a></span></li>
            @endif
            @if($whatsappDigits)
              <li><i class="bi bi-check-circle-fill"></i> <span><a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener">{{ __('public_site.actions.whatsapp_us') }}</a></span></li>
            @endif
            @if(filled($business->email))
              <li><i class="bi bi-check-circle-fill"></i> <span><a href="mailto:{{ $business->email }}">{{ $business->email }}</a></span></li>
            @endif
            @if($hoursText !== '')
              <li><i class="bi bi-check-circle-fill"></i> <span>{{ $hoursText }}</span></li>
            @endif
          </ul>
          <div class="d-flex flex-wrap gap-2 mt-4">
            @if($whatsappDigits)
              <a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener" class="btn-getstarted px-4 py-2"><i class="bi bi-whatsapp"></i> {{ __('public_site.actions.whatsapp') }}</a>
            @endif
            @if($business->phone)
              <a href="tel:{{ $business->phone }}" class="cta-btn px-4 py-2"><i class="bi bi-telephone"></i> {{ __('public_site.actions.call') }}</a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endif

@if($showShare)
<section id="share" class="section site-share-section">
  <div class="container" data-aos="fade-up">
    <div class="site-share-box">
      <div class="site-share-box__text">
        <h2>{{ __('public_site.share.title') }}</h2>
        <p>{{ __('public_site.share.hint') }}</p>
        <div class="d-flex flex-wrap gap-2">
          <button type="button"
                  class="btn-getstarted px-4 py-2"
                  data-site-share
                  data-share-url="{{ $website->publicUrl() }}"
                  data-share-title="{{ $business->name }}"
                  data-share-text="{{ $website->tagline ?: $business->name }}"
                  data-copied-label="{{ __('public_site.actions.share_copied') }}">
            <i class="bi bi-link-45deg"></i> {{ __('public_site.share.copy') }}
          </button>
          @if($whatsappDigits)
            @php
              $shareWa = urlencode(($business->name).' — '.$website->publicUrl());
            @endphp
            <a href="https://wa.me/?text={{ $shareWa }}" target="_blank" rel="noopener" class="cta-btn px-4 py-2">
              <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
          @endif
        </div>
      </div>
      @if($scanMenuImage)
      <div class="site-share-box__qr">
        <img src="{{ $scanMenuImage }}" alt="{{ __('public_site.share.qr') }}" class="site-scan-menu-gif" loading="lazy" width="220" height="220">
        <span>{{ __('public_site.share.scan') }}</span>
        <a href="{{ $website->qrCodeUrl(400) }}" class="site-share-box__qr-link" target="_blank" rel="noopener">
          {{ __('public_site.share.qr') }}
        </a>
      </div>
      @endif
    </div>
  </div>
</section>
@endif
@endsection

@push('styles')
<style>
  /* Hero: uploaded image + readable white title (GP .hero h2 defaults to dark text) */
  .business-site .business-hero.hero {
    background-color: #1a1510;
    background-position: center center;
    background-size: cover;
    background-repeat: no-repeat;
    min-height: 460px;
    max-height: none;
    height: auto;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 140px 0 90px;
  }
  /* Force uploaded media to fill their boxes */
  .business-site img.site-fit-cover,
  .business-site .site-fit-cover img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center center !important;
    display: block;
  }
  .business-site .business-hero.hero::before { display: none !important; }
  .business-site .business-hero .business-hero__shade {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: linear-gradient(160deg, rgba(0,0,0,.45) 0%, rgba(0,0,0,.3) 50%, rgba(0,0,0,.5) 100%);
  }
  .business-site .business-hero > .container { position: relative; z-index: 2; }
  .business-site .business-hero__title {
    color: #fff !important;
    font-size: clamp(1.55rem, 4.2vw, 2.85rem) !important;
    line-height: 1.2 !important;
    font-weight: 800 !important;
    margin: 0 !important;
    text-shadow: 0 2px 18px rgba(0,0,0,.45);
    word-break: break-word;
  }
  .business-site .business-hero__sub {
    color: rgba(255,255,255,.9) !important;
    font-size: clamp(1rem, 2vw, 1.25rem) !important;
    line-height: 1.45 !important;
    text-shadow: 0 1px 10px rgba(0,0,0,.4);
  }

  /* About — Why Us numbered cards */
  .why-section {
    background: #0c0b09;
    color: #fff;
    padding: 80px 0;
  }
  .why-cards { margin-top: .5rem; }
  .why-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .75rem;
    color: color-mix(in srgb, var(--accent-color) 70%, #e0c08a);
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    margin-bottom: .85rem;
  }
  .why-eyebrow::after {
    content: "";
    width: 56px;
    height: 1px;
    background: color-mix(in srgb, var(--accent-color) 70%, #e0c08a);
  }
  .why-title {
    color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8);
    font-family: 'Raleway', Georgia, serif;
    font-size: clamp(1.75rem, 3.5vw, 2.4rem);
    font-weight: 700;
    margin: 0;
  }
  .why-card {
    background: #1a1814;
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 4px;
    padding: 1.75rem 1.5rem 1.6rem;
    transition: transform .25s ease, border-color .25s ease;
  }
  .why-card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--accent-color) 45%, transparent);
  }
  .why-card__num {
    font-family: 'Raleway', Georgia, serif;
    font-size: 2rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8);
    line-height: 1;
    margin-bottom: .85rem;
  }
  .why-card__title {
    color: #fff;
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: .65rem;
  }
  .why-card__body {
    color: rgba(255,255,255,.65);
    font-size: .95rem;
    line-height: 1.65;
  }

  /* Contact / Order — split feature blocks */
  .feature-split-section {
    position: relative;
    background: var(--site-panel-bg, #0c0b09);
    color: var(--site-panel-text, #fff);
    padding: 80px 0;
    overflow: hidden;
    transition: background-color .25s ease, color .25s ease;
  }
  .feature-split-section--alt {
    background: var(--site-panel-bg-alt, #11100e);
  }
  .feature-split__media {
    position: relative;
    height: 100%;
    min-height: 320px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,.35);
    background: #1a1814;
  }
  .feature-split__media img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    object-position: center center;
    display: block;
    min-height: 320px;
  }
  /* Equal columns: text and steps image share the same width & height */
  #order .order-split-row > [class*="col-"] {
    display: flex;
  }
  #order .feature-split__content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 420px;
  }
  #order .feature-split__media--steps {
    width: 100%;
    height: 100%;
    min-height: 420px;
    max-height: none;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #order .feature-split__media--steps img {
    position: relative;
    inset: auto;
    width: 100% !important;
    height: auto !important;
    max-width: 100%;
    max-height: 100%;
    min-height: 0 !important;
    object-fit: contain;
    object-position: center;
    padding: 1rem;
    background: #fff;
  }
  #contact .feature-split__media--map,
  #contact .feature-split__content {
    min-height: 420px;
    width: 100%;
  }
  @media (max-width: 991.98px) {
    #order .feature-split__content,
    #order .feature-split__media--steps,
    #contact .feature-split__media--map,
    #contact .feature-split__content {
      min-height: 300px;
    }
  }
  .feature-split__media--map iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    filter: grayscale(.15) contrast(1.05);
  }
  .feature-split__map-link {
    position: absolute;
    left: 12px;
    bottom: 12px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .85rem;
    border-radius: 999px;
    background: rgba(12, 11, 9, .88);
    color: #fff !important;
    font-size: .85rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid rgba(255,255,255,.15);
  }
  .feature-split__map-link:hover {
    background: var(--accent-color);
    color: #fff !important;
  }
  .feature-split__title {
    color: var(--site-panel-text, #fff);
    font-family: 'Raleway', Georgia, serif;
    font-size: clamp(1.6rem, 3vw, 2.1rem);
    font-weight: 700;
    margin: .35rem 0 1rem;
  }
  .feature-split__lead {
    color: var(--site-panel-muted, rgba(255,255,255,.7));
    font-style: italic;
    margin-bottom: 1.25rem;
  }
  .feature-split__list {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .feature-split__list li {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    margin-bottom: .85rem;
    color: var(--site-panel-soft, rgba(255,255,255,.88));
  }
  .feature-split__list i {
    color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8);
    margin-top: .15rem;
  }
  .feature-split__list a {
    color: var(--site-panel-text, #fff);
    text-decoration: none;
    border-bottom: 1px solid color-mix(in srgb, var(--site-panel-text, #fff) 25%, transparent);
  }
  .feature-split__list a:hover { color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8); }

  @media (max-width: 767px) {
    .business-site .business-hero.hero { min-height: 380px; }
    .feature-split__media { min-height: 220px; }
  }

  .site-promo-section {
    background: var(--site-panel-bg-alt, #11100e);
    color: var(--site-panel-text, #fff);
    padding: 48px 0 20px;
  }
  .site-promo__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .85rem;
  }
  .site-promo__card {
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    padding: 1rem 1.1rem;
    border-radius: 10px;
    background: color-mix(in srgb, var(--accent-color) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--accent-color) 28%, transparent);
    font-weight: 600;
    line-height: 1.35;
  }
  .site-promo__card i { color: var(--accent-color); margin-top: .15rem; }

  .site-order-notes {
    display: grid;
    gap: .75rem;
    margin-top: 1.25rem;
  }
  .site-order-note {
    padding: .85rem 1rem;
    border-radius: 8px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
  }
  html[data-site-theme="light"] .site-order-note {
    background: rgba(0,0,0,.03);
    border-color: rgba(0,0,0,.08);
  }
  .site-order-note strong {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8);
    font-size: .9rem;
  }
  .site-order-note p {
    margin: .35rem 0 0;
    color: var(--site-panel-muted, rgba(255,255,255,.7));
    font-size: .92rem;
  }

  .site-gallery-section {
    background: var(--site-panel-bg, #0c0b09);
    color: var(--site-panel-text, #fff);
    padding: 70px 0;
  }
  .site-gallery-section .section-title h2 { color: var(--site-panel-text, #fff); }
  .site-gallery-section .section-title p { color: var(--site-panel-muted, rgba(255,255,255,.7)); }
  .site-gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
  }
  .site-gallery-item {
    display: block;
    width: 100%;
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    height: auto;
    background: #1a1814;
    position: relative;
  }
  .site-gallery-item img {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
    max-width: none;
    max-height: none;
    object-fit: cover;
    object-position: center;
    display: block;
    transition: transform .35s ease;
  }
  .site-gallery-item:hover img { transform: scale(1.05); }
  @media (max-width: 991.98px) {
    .site-gallery-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .site-gallery-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }

  .site-share-section {
    background: var(--site-panel-bg-alt, #11100e);
    color: var(--site-panel-text, #fff);
    padding: 70px 0;
  }
  .site-share-box {
    display: grid;
    grid-template-columns: 1.4fr .8fr;
    gap: 2rem;
    align-items: center;
    padding: 1.75rem;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,.08);
    background: rgba(255,255,255,.03);
  }
  html[data-site-theme="light"] .site-share-box {
    border-color: rgba(0,0,0,.08);
    background: #fff;
  }
  .site-share-box h2 {
    font-family: 'Raleway', sans-serif;
    font-size: clamp(1.4rem, 3vw, 1.9rem);
    margin: 0 0 .5rem;
  }
  .site-share-box p { color: var(--site-panel-muted); margin-bottom: 1.1rem; }
  .site-share-box__qr {
    text-align: center;
  }
  .site-share-box__qr {
    width: 220px;
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
  }
  .site-share-box__qr img,
  .site-share-box__qr .site-scan-menu-gif {
    width: 220px !important;
    height: 220px !important;
    max-width: 100%;
    object-fit: contain;
    object-position: center;
    border-radius: 12px;
    background: transparent;
    padding: 0;
    display: block;
    margin: 0 auto;
  }
  .site-share-box__qr span {
    display: block;
    margin-top: .65rem;
    font-size: .82rem;
    color: var(--site-panel-muted);
  }
  .site-share-box__qr-link {
    display: inline-block;
    margin-top: .45rem;
    font-size: .78rem;
    font-weight: 600;
    color: color-mix(in srgb, var(--accent-color) 70%, #fff);
    text-decoration: underline;
  }
  @media (max-width: 767px) {
    .site-share-box { grid-template-columns: 1fr; text-align: center; }
    .site-share-box .d-flex { justify-content: center; }
  }
</style>
@endpush
