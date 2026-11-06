<section id="modules" class="modules section">
  <div class="container" data-aos="fade-up">
    <div class="section-title text-center">
      <h2>{{ __('landing.modules.kicker') }}</h2>
      <p>{{ __('landing.modules.title') }}</p>
    </div>
    <p class="text-center text-muted mb-4 mx-auto" style="max-width: 720px;">{{ __('landing.modules.lead') }}</p>

    <div class="row gy-4 align-items-center">
      <div class="col-lg-7" data-aos="fade-right" data-aos-delay="100">
        <img
          src="{{ asset('gp-assets/img/mauzolink-modules-flow.jpg') }}?v={{ @filemtime(public_path('gp-assets/img/mauzolink-modules-flow.jpg')) }}"
          class="img-fluid rounded shadow-sm"
          alt="{{ __('landing.modules.image_alt') }}"
        >
      </div>
      <div class="col-lg-5" data-aos="fade-left" data-aos-delay="150">
        <h4 class="mb-3" style="color: #1a1a1a; font-weight: 700;">{{ __('landing.modules.list_title') }}</h4>
        <ul class="ml-modules-checklist list-unstyled mb-0">
          @foreach(__('landing.modules.items') as $item)
            <li><i class="bi bi-check2"></i> <span>{{ $item }}</span></li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</section>
