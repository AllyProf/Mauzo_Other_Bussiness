@php
  $appName = $platformName ?? 'Mauzo Link';
  $screens = __('landing.mobile.screens');
@endphp
<section id="mobile-app" class="mobile-app section">
  <div class="container section-title" data-aos="fade-up">
    <h2>{{ __('landing.mobile.section_title') }}</h2>
    <p>{{ __('landing.mobile.section_sub') }}</p>
  </div>

  <div class="container">
    <div class="row gy-5 align-items-center">
      <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
        <h3 class="mobile-app__title">{{ __('landing.mobile.title', ['app' => $appName]) }}</h3>
        <p class="mobile-app__lead">{{ __('landing.mobile.lead') }}</p>

        <ul class="mobile-app__list">
          @foreach(__('landing.mobile.list') as $row)
          <li><i class="bi {{ $row['icon'] }}"></i><div><strong>{{ $row['title'] }}</strong><span>{{ $row['text'] }}</span></div></li>
          @endforeach
        </ul>

        <div class="mobile-app__stores">
          <div class="mobile-app__store-logo-wrap">
            <img
              src="{{ asset('gp-assets/img/google_and_app_store_logo.png') }}"
              alt="{{ __('landing.mobile.google_play') }} & {{ __('landing.mobile.app_store') }}"
              class="mobile-app__store-logo"
              loading="lazy"
              decoding="async"
            >
            <a
              class="mobile-app__store-hit mobile-app__store-hit--google"
              href="https://play.google.com/store/apps/details?id=com.mauzolink.mauzolink_pos&pcampaignid=web_share"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="{{ __('landing.mobile.google_play') }}"
            ></a>
          </div>
        </div>
      </div>

      <div class="col-lg-7" data-aos="fade-up" data-aos-delay="200">
        @php
          $screenImages = ['gp-assets/img/mobile_img2.png', 'gp-assets/img/mobile_img.png'];
        @endphp
        <div id="mobileAppCarousel" class="carousel slide carousel-fade mobile-app__carousel" data-bs-ride="carousel" data-bs-interval="6000">
          <div class="carousel-inner">
            @foreach($screens as $i => $screen)
              <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                <a href="{{ asset($screenImages[$i] ?? $screenImages[0]) }}" class="glightbox" data-gallery="mobile-app" data-title="{{ $screen['tab'] }}" data-description="{{ $screen['caption'] }}">
                  <img src="{{ asset($screenImages[$i] ?? $screenImages[0]) }}" class="d-block w-100" alt="{{ $appName }} — {{ $screen['tab'] }}" loading="lazy">
                </a>
                <p class="mobile-app__caption"><i class="bi bi-zoom-in"></i> {{ $screen['caption'] }}</p>
              </div>
            @endforeach
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#mobileAppCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">{{ __('landing.mobile.carousel_prev') }}</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#mobileAppCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">{{ __('landing.mobile.carousel_next') }}</span>
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
  .mobile-app__stores { margin-top: 18px; }
  .mobile-app__store-logo-wrap { position: relative; display: inline-block; width: min(100%, 360px); }
  .mobile-app__store-logo { display: block; width: 100%; height: auto; }
  .mobile-app__store-hit { position: absolute; top: 0; bottom: 0; width: 50%; }
  .mobile-app__store-hit--google { right: 0; }
  .mobile-app__title { margin: 0 0 10px; font-weight: 700; font-size: 28px; }
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

