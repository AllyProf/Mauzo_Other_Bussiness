<section id="regions" class="regions section">
  <div class="container" data-aos="fade-up">
    <div class="section-title text-center">
      <h2>{{ __('landing.regions.kicker') }}</h2>
      <p>{{ __('landing.regions.title') }}</p>
    </div>
    <p class="text-center text-muted mb-4 mx-auto" style="max-width: 640px;">{{ __('landing.regions.lead') }}</p>
    <div class="text-center" data-aos="zoom-in" data-aos-delay="100">
      <img
        src="{{ asset('gp-assets/img/mauzolink-regions-map.jpg') }}?v={{ @filemtime(public_path('gp-assets/img/mauzolink-regions-map.jpg')) }}"
        class="img-fluid rounded shadow-sm"
        alt="{{ __('landing.regions.image_alt') }}"
        style="max-width: 920px; width: 100%;"
      >
    </div>
    <ul class="ml-regions-list list-unstyled d-flex flex-wrap justify-content-center gap-2 mt-4 mb-0">
      @foreach(__('landing.regions.pins') as $region)
        <li><span class="ml-regions-chip"><i class="bi bi-geo-alt-fill"></i> {{ $region }}</span></li>
      @endforeach
    </ul>
  </div>
</section>
