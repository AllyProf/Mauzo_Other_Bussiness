<section id="features" class="features section">
  <div class="container">
    <div class="row gy-4">
      <div class="features-image col-lg-6" data-aos="fade-up" data-aos-delay="100">
        <img src="{{ asset('gp-assets/img/features-analytics.png') }}" class="img-fluid rounded" alt="{{ __('landing.features.image_alt') }}">
      </div>
      <div class="col-lg-6">
        @foreach(__('landing.features.items') as $i => $feature)
        <div class="features-item d-flex ps-0 ps-lg-3 {{ $i ? 'mt-5' : 'pt-4 pt-lg-0' }}" data-aos="fade-up" data-aos-delay="{{ 200 + ($i * 100) }}">
          <i class="bi {{ $feature['icon'] }} flex-shrink-0"></i>
          <div>
            <h4>{{ $feature['title'] }}</h4>
            <p>{{ $feature['text'] }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
