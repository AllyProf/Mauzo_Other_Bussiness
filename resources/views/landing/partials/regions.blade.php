@php
  $regionPins = __('landing.regions.pins');
  if (! is_array($regionPins)) {
      $regionPins = [];
  }
  $sidePoints = __('landing.regions.side_points');
  if (! is_array($sidePoints)) {
      $sidePoints = [];
  }
@endphp
<section id="regions" class="regions section">
  <div class="container" data-aos="fade-up">
    <div class="section-title text-center">
      <h2>{{ __('landing.regions.kicker') }}</h2>
      <p>{{ __('landing.regions.title') }}</p>
    </div>

    <div class="row gy-4 align-items-center ml-tz-layout">
      <div class="col-lg-6" data-aos="fade-right" data-aos-delay="80">
        <div class="ml-tz-map">
          <div class="ml-tz-map__stage" id="mlTzMapStage">
            <div class="ml-tz-map__scan" aria-hidden="true"></div>
            <img
              class="ml-tz-map__base"
              src="{{ asset('gp-assets/img/tanzania-map-clean.jpg') }}"
              alt="{{ __('landing.regions.image_alt') }}"
              width="800"
              height="800"
              loading="lazy"
              decoding="async"
            >

            @foreach($regionPins as $i => $pin)
              <button
                type="button"
                class="ml-tz-pulse ml-tz-pulse--label-{{ $pin['label'] ?? 'right' }}"
                style="left: {{ $pin['x'] }}%; top: {{ $pin['y'] }}%; --ml-pulse-delay: {{ $i * 0.28 }}s;"
                data-region="{{ $pin['name'] }}"
                data-pin-index="{{ $i }}"
                aria-label="{{ $pin['name'] }}"
              >
                <span class="ml-tz-pulse__ring"></span>
                <span class="ml-tz-pulse__ring ml-tz-pulse__ring--delay"></span>
                <span class="ml-tz-pulse__ring ml-tz-pulse__ring--delay2"></span>
                <span class="ml-tz-pulse__dot"></span>
                <span class="ml-tz-pulse__name">{{ $pin['name'] }}</span>
              </button>
            @endforeach
          </div>
          <p class="ml-tz-map__hint">{{ __('landing.regions.hint') }}</p>
        </div>
      </div>

      <div class="col-lg-6" data-aos="fade-left" data-aos-delay="140">
        <div class="ml-tz-side">
          <span class="ml-tz-side__live">
            <span class="ml-tz-side__live-dot" aria-hidden="true"></span>
            {{ __('landing.regions.side_live') }}
          </span>
          <p class="ml-tz-side__region" id="mlTzLiveRegion">{{ $regionPins[0]['name'] ?? '' }}</p>
          <h3 class="ml-tz-side__title">{{ __('landing.regions.side_title') }}</h3>
          <p class="ml-tz-side__lead">{{ __('landing.regions.side_lead') }}</p>
          <ul class="ml-tz-side__points list-unstyled">
            @foreach($sidePoints as $point)
              <li><i class="bi bi-check2-circle"></i> <span>{{ $point }}</span></li>
            @endforeach
          </ul>
          <a href="{{ route('register.business') }}" class="btn-getstarted px-4 py-2 ml-tz-side__cta">
            {{ __('landing.regions.side_cta') }}
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

@push('scripts')
<script>
(function () {
  var stage = document.getElementById('mlTzMapStage');
  var live = document.getElementById('mlTzLiveRegion');
  if (!stage) return;

  var pins = Array.prototype.slice.call(stage.querySelectorAll('.ml-tz-pulse'));
  if (!pins.length) return;

  var userLocked = false;
  var simIndex = 0;
  var simTimer = null;

  function clearActive() {
    pins.forEach(function (el) { el.classList.remove('is-active'); });
  }

  function showRegion(btn, fromUser) {
    clearActive();
    btn.classList.add('is-active');
    if (live) live.textContent = btn.getAttribute('data-region') || '';
    if (fromUser) {
      userLocked = true;
      stopSim();
    }
  }

  function stopSim() {
    if (simTimer) {
      clearInterval(simTimer);
      simTimer = null;
    }
  }

  function tickSim() {
    if (userLocked) return;
    var btn = pins[simIndex % pins.length];
    showRegion(btn, false);
    simIndex += 1;
  }

  function startSim() {
    stopSim();
    userLocked = false;
    tickSim();
    simTimer = setInterval(tickSim, 2200);
  }

  pins.forEach(function (btn) {
    btn.addEventListener('mouseenter', function () { showRegion(btn, true); });
    btn.addEventListener('focus', function () { showRegion(btn, true); });
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      showRegion(btn, true);
    });
  });

  stage.addEventListener('mouseleave', function () {
    userLocked = false;
    startSim();
  });

  startSim();
})();
</script>
@endpush
