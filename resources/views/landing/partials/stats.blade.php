<section id="stats" class="stats section light-background">
  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="row gy-4 align-items-center justify-content-between">
      <div class="col-lg-5">
        <img src="{{ asset('gp-assets/img/stats-dashboard.png') }}" alt="{{ __('landing.stats.image_alt') }}" class="img-fluid rounded">
      </div>
      <div class="col-lg-6">
        <h3 class="fw-bold fs-2 mb-3">{{ __('landing.stats.title') }}</h3>
        <p>{{ __('landing.stats.lead', ['platform' => $platformName ?? config('app.name')]) }}</p>
        <div class="row gy-4">
          @foreach(__('landing.stats.items') as $stat)
          <div class="col-6">
            <div class="stats-item d-flex">
              <i class="bi {{ $stat['icon'] }} flex-shrink-0"></i>
              <div>
                <span data-purecounter-start="0" data-purecounter-end="{{ $stat['end'] }}" data-purecounter-duration="1" class="purecounter"></span>
                <p><strong>{{ $stat['label'] }}</strong> <span>{{ $stat['sub'] }}</span></p>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>
