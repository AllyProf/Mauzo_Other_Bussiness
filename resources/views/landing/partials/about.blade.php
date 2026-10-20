<section id="about" class="about section">
  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="row gy-4">
      <div class="col-lg-6 order-1 order-lg-2">
        <img src="{{ asset('gp-assets/img/about-pos.png') }}" class="img-fluid rounded shadow" style="width: 100%; aspect-ratio: 4/3; object-fit: cover;" alt="{{ __('landing.about.image_alt') }}">
      </div>
      <div class="col-lg-6 order-2 order-lg-1 content">
        <h2>{{ __('landing.about.title') }}</h2>
        <p class="fst-italic">{{ __('landing.about.lead') }}</p>
        <ul>
          @foreach(__('landing.about.points') as $point)
          <li><i class="bi bi-check2-all"></i> <span><strong>{{ $point['title'] }}</strong> {{ $point['text'] }}</span></li>
          @endforeach
        </ul>
        <p>{{ __('landing.about.closing') }}</p>
      </div>
    </div>
  </div>
</section>
