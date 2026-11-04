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
      $productsCaption = $totalInStock > 0 ? 'In stock with prices.' : 'Visit us to see what is available.';
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

  $contactTitle = $contact['title'] ?? 'Contact';
  $contactCaption = trim((string) ($contact['body'] ?? ''));
  if ($isBoilerplate($contactCaption) || strlen($contactCaption) > 90) {
      $contactCaption = 'Call or WhatsApp us.';
  }

  $orderTitle = $order['title'] ?? 'Place an order';
  $orderCaption = trim((string) ($order['body'] ?? ''));
  if ($isBoilerplate($orderCaption) || strlen($orderCaption) > 90) {
      $orderCaption = 'Order by WhatsApp or phone.';
  }
  $orderSteps = collect(preg_split('/\r\n|\r|\n/', (string) ($order['steps'] ?? '')))
      ->map(fn ($s) => trim(preg_replace('/^\d+[\.\)]\s*/', '', $s)))
      ->filter()
      ->values()
      ->all();
  if ($orderSteps === []) {
      $orderSteps = [
          'Send your list on WhatsApp or call us.',
          'We confirm price and stock.',
          'Pay and collect or receive delivery.',
      ];
  }

  $heroImage = $website->heroImageUrl();
  $orderFeatureImage = asset('gp-assets/img/business-site/order-steps.jpg');
  $mapQuery = trim((string) ($business->address ?? ''));
  if ($mapQuery === '') {
      $mapQuery = (string) ($business->name ?? 'Tanzania');
  }
  $mapEmbedSrc = 'https://maps.google.com/maps?q='.rawurlencode($mapQuery).'&z=15&output=embed';
  $mapOpenUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($mapQuery);
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
          <a href="#products" class="cta-btn px-4 py-2">View products</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="products" class="section menu-section">
  <div class="container section-title section-title--light" data-aos="fade-up">
    <h2>{{ $products['title'] ?? ($content['intro_title'] ?? 'Products') }}</h2>
    <p>{{ $productsCaption }}</p>
  </div>
  <div class="container" data-aos="fade-up" data-aos-delay="100">
    @include('public-site.partials.catalog-offerings', ['catalog' => $catalog, 'business' => $business])
    @if($totalInStock > 0)
      <div class="text-center mt-5 pt-2">
        <a href="#order" class="btn-getstarted px-4 py-2 me-2"><i class="bi bi-cart-check"></i> Order now</a>
        <a href="#contact" class="cta-btn px-4 py-2">Contact us</a>
      </div>
    @endif
  </div>
</section>

<section id="contact" class="section feature-split-section">
  <div class="container" data-aos="fade-up">
    <div class="row align-items-stretch g-4 g-lg-5">
      <div class="col-lg-6">
        <div class="feature-split__media feature-split__media--map">
          <iframe
            title="Map — {{ $business->name }}"
            src="{{ $mapEmbedSrc }}"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen></iframe>
          <a class="feature-split__map-link" href="{{ $mapOpenUrl }}" target="_blank" rel="noopener">
            <i class="bi bi-geo-alt-fill"></i> Open in Google Maps
          </a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="feature-split__content">
          <span class="why-eyebrow">Get in touch</span>
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
              <li><i class="bi bi-check-circle-fill"></i> <span><a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener">WhatsApp us</a></span></li>
            @endif
            @if(filled($business->email))
              <li><i class="bi bi-check-circle-fill"></i> <span><a href="mailto:{{ $business->email }}">{{ $business->email }}</a></span></li>
            @endif
            @if(!empty($contact['hours']))
              <li><i class="bi bi-check-circle-fill"></i> <span>{{ $contact['hours'] }}</span></li>
            @endif
          </ul>
          <div class="d-flex flex-wrap gap-2 mt-4">
            @if($whatsappDigits)
              <a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener" class="btn-getstarted px-4 py-2"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            @endif
            @if($business->phone)
              <a href="tel:{{ $business->phone }}" class="cta-btn px-4 py-2"><i class="bi bi-telephone"></i> Call</a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="order" class="section feature-split-section feature-split-section--alt">
  <div class="container" data-aos="fade-up">
    <div class="row align-items-stretch g-4 g-lg-5 flex-lg-row-reverse order-split-row">
      <div class="col-lg-6 d-flex">
        <div class="feature-split__media feature-split__media--steps w-100">
          <img src="{{ $orderFeatureImage }}" alt="How to place an order — three steps">
        </div>
      </div>
      <div class="col-lg-6 d-flex">
        <div class="feature-split__content w-100">
          <span class="why-eyebrow">Ordering</span>
          <h2 class="feature-split__title">{{ $orderTitle }}</h2>
          <p class="feature-split__lead">{{ $orderCaption }}</p>
          <ul class="feature-split__list">
            @foreach($orderSteps as $step)
              <li><i class="bi bi-check-circle-fill"></i> <span>{{ $step }}</span></li>
            @endforeach
          </ul>
          <div class="d-flex flex-wrap gap-2 mt-4">
            @if($whatsappDigits)
              @php $waMsg = urlencode($order['whatsapp_message'] ?? ('Hello '.$business->name.', I would like to order: ')); @endphp
              <a href="https://wa.me/{{ $whatsappDigits }}?text={{ $waMsg }}" target="_blank" rel="noopener" class="btn-getstarted px-4 py-2">
                <i class="bi bi-whatsapp"></i> Order on WhatsApp
              </a>
            @endif
            @if($business->phone)
              <a href="tel:{{ $business->phone }}" class="cta-btn px-4 py-2"><i class="bi bi-telephone"></i> Call to order</a>
            @endif
            <a href="#products" class="cta-btn px-4 py-2">View products</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('styles')
<style>
  /* Hero: uploaded image + readable white title (GP .hero h2 defaults to dark text) */
  .business-site .business-hero.hero {
    background-color: #1a1510;
    background-position: center;
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

  /* Contact / Order — split feature blocks (solid dark bg; photo only in media box) */
  .feature-split-section {
    position: relative;
    background: #0c0b09;
    color: #fff;
    padding: 80px 0;
    overflow: hidden;
  }
  .feature-split-section--alt {
    background: #11100e;
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
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    min-height: 320px;
  }
  /* Keep order text + steps image the same panel size */
  #order .order-split-row > [class*="col-"] {
    display: flex;
  }
  #order .feature-split__content {
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  #order .feature-split__media--steps {
    min-height: 0;
    height: 100%;
    max-height: 420px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0c0b09;
  }
  #order .feature-split__media--steps img {
    position: static;
    width: auto;
    max-width: 100%;
    height: auto;
    max-height: 100%;
    min-height: 0 !important;
    object-fit: contain;
    padding: .75rem;
    background: transparent;
  }
  @media (max-width: 991.98px) {
    #order .feature-split__media--steps {
      max-height: 360px;
      min-height: 260px;
      width: 100%;
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
    color: #fff;
    font-family: 'Raleway', Georgia, serif;
    font-size: clamp(1.6rem, 3vw, 2.1rem);
    font-weight: 700;
    margin: .35rem 0 1rem;
  }
  .feature-split__lead {
    color: rgba(255,255,255,.7);
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
    color: rgba(255,255,255,.88);
  }
  .feature-split__list i {
    color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8);
    margin-top: .15rem;
  }
  .feature-split__list a {
    color: #fff;
    text-decoration: none;
    border-bottom: 1px solid rgba(255,255,255,.25);
  }
  .feature-split__list a:hover { color: color-mix(in srgb, var(--accent-color) 55%, #f0d9a8); }

  @media (max-width: 767px) {
    .business-site .business-hero.hero { min-height: 380px; }
    .feature-split__media { min-height: 220px; }
  }
</style>
@endpush
