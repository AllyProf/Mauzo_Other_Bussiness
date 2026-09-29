@php
  $appName = $platformName ?? 'Mauzo Link';
  $screens = [
      ['img' => 'gp-assets/img/mobile_img2.png', 'tab' => 'Selling & Dashboard', 'caption' => 'Sell, scan and follow today\'s orders, collections and profit.'],
      ['img' => 'gp-assets/img/mobile_img.png', 'tab' => 'Secure Login', 'caption' => 'Sign in with password or fingerprint, in Swahili or English.'],
  ];
@endphp
<section id="mobile-app" class="mobile-app section">
  <div class="container section-title" data-aos="fade-up">
    <h2>Mobile App</h2>
    <p>Run your business from your pocket</p>
  </div>

  <div class="container">
    <div class="row gy-5 align-items-center">
      <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
        <div class="mobile-app__platforms">
          <span class="mobile-app__badge"><i class="bi bi-android2"></i> Android</span>
          <span class="mobile-app__badge"><i class="bi bi-apple"></i> iOS</span>
        </div>
        <h3 class="mobile-app__title">{{ $appName }} POS, wherever you are</h3>
        <p class="mobile-app__lead">
          Everything your team does at the counter, now on Android and iPhone. Same account, same data,
          synced live with the web dashboard.
        </p>

        <ul class="mobile-app__list">
          <li><i class="bi bi-upc-scan"></i><div><strong>Sell on the go</strong><span>Search items or scan QR codes, with stock and packaging shown on every product.</span></div></li>
          <li><i class="bi bi-speedometer2"></i><div><strong>Today at a glance</strong><span>Orders, collected cash, outstanding debts and profit, updated as you sell.</span></div></li>
          <li><i class="bi bi-calendar-check"></i><div><strong>Day close from your phone</strong><span>Hand over the shift, record expenses and send it for the owner's verification.</span></div></li>
          <li><i class="bi bi-fingerprint"></i><div><strong>Fast, secure sign-in</strong><span>Fingerprint login and full Swahili &amp; English support.</span></div></li>
        </ul>

        <div class="mobile-app__actions">
          @if($registrationOpen ?? true)
            <a href="{{ route('register.business') }}" class="mobile-app__btn mobile-app__btn--primary">
              <i class="bi bi-rocket-takeoff"></i> Get Started
            </a>
          @endif
          <a href="{{ route('landing.index') }}#contact" class="mobile-app__btn mobile-app__btn--outline">
            <i class="bi bi-phone"></i> Request the App
          </a>
        </div>

        <div class="mobile-app__stores">
          <div class="mobile-app__store">
            <i class="bi bi-google-play"></i>
            <div><small>Available on</small><strong>Google Play</strong></div>
          </div>
          <div class="mobile-app__store">
            <i class="bi bi-apple"></i>
            <div><small>Download on the</small><strong>App Store</strong></div>
          </div>
        </div>
      </div>

      <div class="col-lg-7" data-aos="fade-up" data-aos-delay="200">
        <div class="mobile-app__tabs" role="tablist">
          @foreach($screens as $i => $screen)
            <button type="button" class="mobile-app__tab {{ $i === 0 ? 'active' : '' }}"
                    data-bs-target="#mobileAppCarousel" data-bs-slide-to="{{ $i }}"
                    @if($i === 0) aria-current="true" @endif aria-label="{{ $screen['tab'] }}">
              {{ $screen['tab'] }}
            </button>
          @endforeach
        </div>

        <div id="mobileAppCarousel" class="carousel slide carousel-fade mobile-app__carousel" data-bs-ride="carousel" data-bs-interval="6000">
          <div class="carousel-inner">
            @foreach($screens as $i => $screen)
              <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                <a href="{{ asset($screen['img']) }}" class="glightbox" data-gallery="mobile-app" data-title="{{ $screen['tab'] }}" data-description="{{ $screen['caption'] }}">
                  <img src="{{ asset($screen['img']) }}" class="d-block w-100" alt="{{ $appName }} mobile app — {{ $screen['tab'] }}" loading="lazy">
                </a>
                <p class="mobile-app__caption"><i class="bi bi-zoom-in"></i> {{ $screen['caption'] }}</p>
              </div>
            @endforeach
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#mobileAppCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#mobileAppCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

@push('styles')
<style>
  .mobile-app { background: linear-gradient(180deg, #fff 0%, #fbf3f3 100%); }
  .mobile-app__badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; background: rgba(148, 0, 0, .08); color: #940000; font-size: 13px; font-weight: 600; }
  .mobile-app__platforms { display: flex; gap: 8px; flex-wrap: wrap; }
  .mobile-app__stores { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
  .mobile-app__store { display: inline-flex; align-items: center; gap: 10px; padding: 8px 16px; border-radius: 10px; background: #111; color: #fff; min-width: 170px; }
  .mobile-app__store i { font-size: 24px; }
  .mobile-app__store small { display: block; font-size: 10px; line-height: 1.1; opacity: .8; }
  .mobile-app__store strong { display: block; font-size: 16px; line-height: 1.2; }
  .mobile-app__title { margin: 14px 0 10px; font-weight: 700; font-size: 28px; }
  .mobile-app__lead { color: #555; margin-bottom: 22px; }
  .mobile-app__list { list-style: none; padding: 0; margin: 0 0 26px; }
  .mobile-app__list li { display: flex; gap: 14px; margin-bottom: 16px; }
  .mobile-app__list li > i { flex: 0 0 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #940000; color: #fff; font-size: 18px; }
  .mobile-app__list strong { display: block; margin-bottom: 2px; }
  .mobile-app__list span { color: #666; font-size: 14px; }
  .mobile-app__actions { display: flex; flex-wrap: wrap; gap: 12px; }
  .mobile-app__btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: 30px; font-weight: 600; transition: .2s; }
  .mobile-app__btn--primary { background: #940000; color: #fff; border: 2px solid #940000; }
  .mobile-app__btn--primary:hover { background: #6b0000; border-color: #6b0000; color: #fff; }
  .mobile-app__btn--outline { border: 2px solid #940000; color: #940000; }
  .mobile-app__btn--outline:hover { background: #940000; color: #fff; }
  .mobile-app__tabs { display: flex; justify-content: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
  .mobile-app__tab { border: 1px solid rgba(148, 0, 0, .25); background: #fff; color: #940000; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; transition: .2s; }
  .mobile-app__tab.active, .mobile-app__tab:hover { background: #940000; color: #fff; border-color: #940000; }
  .mobile-app__carousel { border-radius: 16px; overflow: hidden; background: #fff; box-shadow: 0 18px 40px rgba(0, 0, 0, .12); }
  .mobile-app__carousel img { aspect-ratio: 3 / 2; object-fit: cover; }
  .mobile-app__caption { margin: 0; padding: 12px 16px; text-align: center; color: #666; font-size: 14px; border-top: 1px solid #f0e6e6; }
  .mobile-app__carousel .carousel-control-prev, .mobile-app__carousel .carousel-control-next { width: 44px; height: 44px; top: calc(50% - 44px); margin: 0 10px; border-radius: 50%; background: rgba(148, 0, 0, .75); opacity: 0; transition: opacity .2s; }
  .mobile-app__carousel:hover .carousel-control-prev, .mobile-app__carousel:hover .carousel-control-next { opacity: 1; }
  .mobile-app__carousel .carousel-control-prev-icon, .mobile-app__carousel .carousel-control-next-icon { width: 18px; height: 18px; }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var carousel = document.getElementById('mobileAppCarousel');
    if (!carousel) return;
    var tabs = document.querySelectorAll('.mobile-app__tab');
    carousel.addEventListener('slide.bs.carousel', function (e) {
      tabs.forEach(function (tab, i) {
        tab.classList.toggle('active', i === e.to);
        if (i === e.to) tab.setAttribute('aria-current', 'true'); else tab.removeAttribute('aria-current');
      });
    });
  });
</script>
@endpush
