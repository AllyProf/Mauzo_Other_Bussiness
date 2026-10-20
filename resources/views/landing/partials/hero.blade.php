<section id="hero" class="hero section dark-background">
  <img src="{{ asset('gp-assets/img/hero-bg.png') }}" alt="" role="presentation" data-aos="fade-in">

  <div class="container">
    <div class="row justify-content-center text-center" data-aos="fade-up" data-aos-delay="100">
      <div class="col-xl-8 col-lg-10">
        <h1 class="hero-title">{{ __('landing.hero.title', ['platform' => $platformName ?? config('app.name')]) }}<span>.</span></h1>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
          @if($registrationOpen)
            <a href="{{ route('register.business') }}" class="btn-getstarted px-4 py-2">{{ __('landing.hero.start_trial') }}</a>
          @endif
          <a href="#contact" class="cta-btn px-4 py-2">{{ __('landing.hero.book_demo') }}</a>
        </div>
      </div>
    </div>

    <div class="row gy-4 mt-5 justify-content-center" data-aos="fade-up" data-aos-delay="200">
      @foreach(__('landing.hero.highlights') as $i => $box)
      <div class="col-xl-3 col-md-6 col-6" data-aos="fade-up" data-aos-delay="{{ 300 + ($i * 100) }}">
        <div class="icon-box">
          <i class="bi {{ $box['icon'] }}"></i>
          <h3><a href="{{ $box['hash'] }}">{{ $box['label'] }}</a></h3>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>
