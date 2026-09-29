@php
  $footerSettings = platform_settings();
  $footerName = $platformName ?? ($footerSettings['platform_name'] ?? config('app.name'));
  $footerPhone = $supportPhone ?? ($footerSettings['support_phone'] ?? '');
  $footerEmail = $supportEmail ?? ($footerSettings['support_email'] ?? '');
  $footerWhatsapp = preg_replace('/\D+/', '', (string) ($footerSettings['support_whatsapp'] ?? ''));
  $footerAddress = $footerSettings['public_address'] ?? '';
  $footerBrand = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($footerSettings['brand_color'] ?? '')) ? $footerSettings['brand_color'] : '#940000';
  $footerSocials = [
      ['url' => $footerSettings['social_instagram'] ?? '', 'fallback' => 'https://www.instagram.com/', 'icon' => 'bi-instagram', 'label' => 'Instagram', 'color' => '#e1306c'],
      ['url' => $footerSettings['social_facebook'] ?? '', 'fallback' => 'https://www.facebook.com/', 'icon' => 'bi-facebook', 'label' => 'Facebook', 'color' => '#1877f2'],
      ['url' => $footerSettings['social_youtube'] ?? '', 'fallback' => 'https://www.youtube.com/', 'icon' => 'bi-youtube', 'label' => 'YouTube', 'color' => '#ff0000'],
      ['url' => $footerSettings['social_tiktok'] ?? '', 'fallback' => 'https://www.tiktok.com/', 'icon' => 'bi-tiktok', 'label' => 'TikTok', 'color' => '#000000'],
      ['url' => $footerSettings['social_x'] ?? '', 'fallback' => 'https://x.com/', 'icon' => 'bi-twitter-x', 'label' => 'X (Twitter)', 'color' => '#000000'],
      ['url' => $footerWhatsapp ? 'https://wa.me/'.$footerWhatsapp : '', 'fallback' => 'https://www.whatsapp.com/', 'icon' => 'bi-whatsapp', 'label' => 'WhatsApp', 'color' => '#25d366'],
  ];
  $home = route('landing.index');
  $footerLinks = [
      ['Home', $home.'#hero'],
      ['About Us', $home.'#about'],
      ['Mobile App', $home.'#mobile-app'],
      ['Pricing', $home.'#pricing'],
      ['FAQ', $home.'#faq'],
      ['Contact Us', $home.'#contact'],
  ];
@endphp
<footer id="footer" class="footer site-footer" style="--footer-brand: {{ $footerBrand }};">
  <svg class="site-footer__wave" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0,0 H1440 V22 C1320,40 1200,14 1080,26 C960,38 840,12 720,24 C600,36 480,10 360,22 C240,34 120,12 0,26 Z" fill="#ffffff"/>
    <path d="M0,26 C120,12 240,34 360,22 C480,10 600,36 720,24 C840,12 960,38 1080,26 C1200,14 1320,40 1440,22" fill="none" stroke="{{ $footerBrand }}" stroke-width="6"/>
    <path d="M0,34 C120,20 240,42 360,30 C480,18 600,44 720,32 C840,20 960,46 1080,34 C1200,22 1320,48 1440,30" fill="none" stroke="{{ $footerBrand }}" stroke-width="2" opacity=".45"/>
  </svg>

  <div class="container site-footer__body">
    <div class="row gy-4">
      <div class="col-lg-3 col-md-6">
        <a href="{{ $home }}" class="site-footer__logo">
          <img src="{{ asset('mauzo_link.png') }}" alt="{{ $footerName }}">
          <span>{{ $footerName }}</span>
        </a>
        <p class="site-footer__about">
          Modern POS and business management for Tanzanian shops — sales, stock, shift closing and
          owner reports in one place, on web and mobile.
        </p>
      </div>

      <div class="col-lg-3 col-md-6 site-footer__col">
        <h4 class="site-footer__heading">Contact Us</h4>
        <ul class="site-footer__list">
          @if(filled($footerAddress))
            <li><i class="bi bi-geo-alt-fill"></i><span>{{ $footerAddress }}</span></li>
          @endif
          @if(filled($footerPhone))
            <li><i class="bi bi-telephone-fill"></i><a href="tel:{{ preg_replace('/[^\d+]/', '', $footerPhone) }}">{{ $footerPhone }}</a></li>
          @endif
          @if(filled($footerEmail))
            <li><i class="bi bi-envelope-fill"></i><a href="mailto:{{ $footerEmail }}">{{ $footerEmail }}</a></li>
          @endif        </ul>
      </div>

      <div class="col-lg-3 col-md-6 site-footer__col">
        <h4 class="site-footer__heading">Quick Links</h4>
        <ul class="site-footer__list site-footer__links">
          @foreach($footerLinks as [$linkLabel, $linkUrl])
            <li><i class="bi bi-check-circle-fill"></i><a href="{{ $linkUrl }}">{{ $linkLabel }}</a></li>
          @endforeach
          <li><i class="bi bi-check-circle-fill"></i><a href="{{ route('login') }}">Sign In</a></li>
          @if($registrationOpen ?? true)
            <li><i class="bi bi-check-circle-fill"></i><a href="{{ route('register.business') }}">Register Business</a></li>
          @endif
        </ul>
      </div>

      <div class="col-lg-3 col-md-6 site-footer__col">
        <h4 class="site-footer__heading">Find us on Digital Platforms</h4>
        <p class="site-footer__about mb-3">Follow us for tips, updates and new features.</p>
        <div class="site-footer__socials">
          @foreach($footerSocials as $social)
            <a href="{{ filled($social['url']) ? $social['url'] : $social['fallback'] }}" target="_blank" rel="noopener"
               class="site-footer__social site-footer__social--{{ \Illuminate\Support\Str::slug(strtok($social['label'], ' ')) }}"
               aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}" style="--social-color: {{ $social['color'] }};">
              <i class="bi {{ $social['icon'] }}"></i>
            </a>
          @endforeach
        </div>
      </div>
    </div>

    <div class="site-footer__bottom">
      <p>
        &copy; {{ date('Y') }} All Rights Reserved By <a href="{{ $home }}">{{ $footerName }}</a>
        <span class="site-footer__sep">|</span>
        <span class="site-footer__powered">Powered by <strong>EmCa Tech</strong></span>
      </p>
    </div>
  </div>
</footer>

<style>
  #footer.site-footer {
    position: relative;
    padding: 0;
    color: rgba(255, 255, 255, .85);
    font-size: 14px;
    background: #111 url("{{ asset('gp-assets/img/footer-bg-pos.jpg') }}") center / cover no-repeat !important;
  }
  #footer.site-footer {
    --footer-brand-light: color-mix(in srgb, var(--footer-brand), #fff 45%);
  }
  #footer.site-footer::before {
    content: "";
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .78);
  }
  .site-footer__wave { position: relative; display: block; width: 100%; height: 60px; z-index: 1; }
  .site-footer__body { position: relative; z-index: 1; padding-top: 50px; }
  .site-footer__logo { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 16px; text-decoration: none; }
  .site-footer__logo img { width: 58px; height: 58px; object-fit: contain; padding: 6px; border-radius: 8px; background: #fff; }
  .site-footer__logo span { color: #fff; font-family: 'Raleway', sans-serif; font-weight: 800; font-size: 20px; }
  .site-footer__about { color: rgba(255, 255, 255, .75); line-height: 1.7; }
  .site-footer__col { padding-left: 24px; border-left: 1px solid rgba(255, 255, 255, .12); }
  #footer .site-footer__heading {
    position: relative;
    margin-bottom: 22px;
    padding-bottom: 10px;
    color: var(--footer-brand-light);
    font-family: 'Poppins', sans-serif;
    font-style: italic;
    font-weight: 600;
    font-size: 17px;
  }
  #footer .site-footer__heading::after { content: ""; position: absolute; left: 0; bottom: 0; width: 42px; height: 3px; border-radius: 2px; background: var(--footer-brand); }
  .site-footer__list { list-style: none; padding: 0; margin: 0; }
  .site-footer__list li { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
  .site-footer__list li i {
    flex: 0 0 26px; width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%; background: var(--footer-brand); color: #fff; font-size: 12px;
  }
  .site-footer__links li i { flex-basis: auto; width: auto; height: auto; background: none; color: var(--footer-brand-light); font-size: 14px; }
  .site-footer__list a, .site-footer__list span { color: rgba(255, 255, 255, .85); text-decoration: none; transition: color .2s, padding .2s; word-break: break-word; }
  .site-footer__list a:hover { color: var(--footer-brand-light); }
  .site-footer__links a:hover { padding-left: 4px; }
  .site-footer__socials { display: grid; grid-template-columns: repeat(3, 48px); gap: 12px; }
  .site-footer__social {
    display: inline-flex; align-items: center; justify-content: center;
    width: 48px; height: 48px; border-radius: 12px;
    background: var(--social-color); color: #fff !important; font-size: 22px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .35);
    transition: transform .2s, box-shadow .2s;
  }
  .site-footer__social--instagram { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285aeb 90%); }
  .site-footer__social--tiktok i { text-shadow: 2px 2px 0 #fe2c55, -2px -2px 0 #25f4ee; }
  .site-footer__social--x, .site-footer__social--tiktok { border: 1px solid rgba(255, 255, 255, .25); }
  .site-footer__social:hover { transform: translateY(-4px); box-shadow: 0 0 0 3px var(--footer-brand), 0 8px 18px rgba(0, 0, 0, .45); }
  .site-footer__bottom { margin-top: 40px; padding: 22px 0 26px; border-top: 1px solid color-mix(in srgb, var(--footer-brand) 50%, transparent); text-align: center; }
  .site-footer__bottom p { margin: 0; }
  .site-footer__bottom a { color: var(--footer-brand-light); font-weight: 600; text-decoration: none; }
  .site-footer__powered { color: rgba(255, 255, 255, .6); }
  .site-footer__sep { margin: 0 10px; color: color-mix(in srgb, var(--footer-brand-light) 70%, transparent); }
  @media (max-width: 991px) {
    .site-footer__col { border-left: 0; padding-left: calc(var(--bs-gutter-x) * .5); }
  }
</style>
