@php
  $brand = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($website->primary_color ?? '')) ? $website->primary_color : '#940000';
  $social = $website->social_links ?? [];
  $wa = preg_replace('/\D+/', '', (string) ($social['whatsapp'] ?? $business->phone ?? ''));
  $footerLinks = collect($nav)->map(fn ($item) => [$item['label'], $item['url']])->all();
@endphp
<footer id="footer" class="footer site-footer" style="--footer-brand: {{ $brand }};">
  <svg class="site-footer__wave" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,0 H1440 V22 C1320,40 1200,14 1080,26 C960,38 840,12 720,24 C600,36 480,10 360,22 C240,34 120,12 0,26 Z" fill="#ffffff"/>
    <path d="M0,26 C120,12 240,34 360,22 C480,10 600,36 720,24 C840,12 960,38 1080,26 C1200,14 1320,40 1440,22" fill="none" stroke="{{ $brand }}" stroke-width="6"/>
  </svg>

  <div class="container site-footer__body">
    <div class="row gy-4">
      <div class="col-lg-4 col-md-6">
        <a href="{{ $website->publicUrl('home') }}" class="site-footer__logo">
          @if($business->logoUrl())
            <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}">
          @else
            <img src="{{ asset('mauzo_link.png') }}" alt="">
          @endif
          <span>{{ $business->name }}</span>
        </a>
        @if($website->tagline)
          <p class="site-footer__about">{{ $website->tagline }}</p>
        @endif
      </div>

      <div class="col-lg-4 col-md-6 site-footer__col">
        <h4 class="site-footer__heading">Contact</h4>
        <ul class="site-footer__list">
          @if(filled($business->address))
            <li><i class="bi bi-geo-alt-fill"></i><span>{{ $business->address }}</span></li>
          @endif
          @if(filled($business->phone))
            <li><i class="bi bi-telephone-fill"></i><a href="tel:{{ $business->phone }}">{{ $business->phone }}</a></li>
          @endif
          @if(filled($business->email))
            <li><i class="bi bi-envelope-fill"></i><a href="mailto:{{ $business->email }}">{{ $business->email }}</a></li>
          @endif
        </ul>
      </div>

      <div class="col-lg-4 col-md-6 site-footer__col">
        <h4 class="site-footer__heading">Quick links</h4>
        <ul class="site-footer__list site-footer__links">
          @foreach($footerLinks as [$label, $url])
            <li><i class="bi bi-check-circle-fill"></i><a href="{{ $url }}">{{ $label }}</a></li>
          @endforeach
        </ul>
        @if($wa || !empty($social['facebook']) || !empty($social['instagram']))
          <div class="site-footer__socials mt-3">
            @if($wa)
              <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="site-footer__social site-footer__social--whatsapp" aria-label="WhatsApp" style="--social-color: #25d366;"><i class="bi bi-whatsapp"></i></a>
            @endif
            @if(!empty($social['facebook']))
              <a href="{{ $social['facebook'] }}" target="_blank" rel="noopener" class="site-footer__social site-footer__social--facebook" aria-label="Facebook" style="--social-color: #1877f2;"><i class="bi bi-facebook"></i></a>
            @endif
            @if(!empty($social['instagram']))
              <a href="{{ $social['instagram'] }}" target="_blank" rel="noopener" class="site-footer__social site-footer__social--instagram" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            @endif
          </div>
        @endif
      </div>
    </div>

    <div class="site-footer__bottom">
      <p>
        &copy; {{ date('Y') }} {{ $business->name }}
        <span class="site-footer__sep">|</span>
        <span class="site-footer__powered">Store site powered by <a href="{{ route('landing.index') }}">Mauzo Link</a></span>
      </p>
    </div>
  </div>
</footer>

@include('public-site.partials.footer-styles')
