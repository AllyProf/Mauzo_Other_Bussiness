@php
  $regionPins = __('landing.regions.pins');
  if (! is_array($regionPins)) {
      $regionPins = [];
  }
@endphp
<section id="regions" class="regions section">
  <div class="container" data-aos="fade-up">
    <div class="section-title text-center">
      <h2>{{ __('landing.regions.kicker') }}</h2>
      <p>{{ __('landing.regions.title') }}</p>
    </div>

    <div class="ml-tz-map" data-aos="zoom-in" data-aos-delay="80">
      <div class="ml-tz-map__stage" id="mlTzMapStage">
        <svg class="ml-tz-map__svg" viewBox="0 0 400 520" role="img" aria-label="{{ __('landing.regions.image_alt') }}">
          {{-- Simplified Tanzania land silhouette --}}
          <path class="ml-tz-map__land" d="M118 42 C148 28 188 22 228 30 C268 38 298 58 318 88 C338 118 348 158 352 198 C356 238 348 278 338 312 C328 346 312 378 288 408 C264 438 232 462 196 478 C160 494 122 498 92 482 C62 466 48 430 42 390 C36 350 38 308 48 268 C58 228 72 188 78 148 C84 108 92 72 118 42 Z" />
          {{-- Zanzibar islands --}}
          <ellipse class="ml-tz-map__land ml-tz-map__island" cx="348" cy="188" rx="18" ry="28" />
          <ellipse class="ml-tz-map__land ml-tz-map__island" cx="362" cy="248" rx="10" ry="16" />
          {{-- Soft lake Victoria}}
          <ellipse class="ml-tz-map__water" cx="92" cy="118" rx="34" ry="48" />
        </svg>

        @foreach($regionPins as $i => $pin)
          <button
            type="button"
            class="ml-tz-pulse"
            style="left: {{ $pin['x'] }}%; top: {{ $pin['y'] }}%; animation-delay: {{ $i * 0.35 }}s;"
            data-region="{{ $pin['name'] }}"
            aria-label="{{ $pin['name'] }}"
          >
            <span class="ml-tz-pulse__ring"></span>
            <span class="ml-tz-pulse__ring ml-tz-pulse__ring--delay"></span>
            <span class="ml-tz-pulse__dot"></span>
          </button>
        @endforeach

        <div class="ml-tz-map__tooltip" id="mlTzMapTooltip" hidden></div>
      </div>
      <p class="ml-tz-map__hint">{{ __('landing.regions.hint') }}</p>
    </div>
  </div>
</section>

@push('scripts')
<script>
(function () {
  var stage = document.getElementById('mlTzMapStage');
  var tip = document.getElementById('mlTzMapTooltip');
  if (!stage || !tip) return;

  function showTip(btn) {
    var name = btn.getAttribute('data-region') || '';
    tip.textContent = name;
    tip.hidden = false;
    tip.style.left = btn.style.left;
    tip.style.top = btn.style.top;
    stage.querySelectorAll('.ml-tz-pulse.is-active').forEach(function (el) {
      el.classList.remove('is-active');
    });
    btn.classList.add('is-active');
  }

  function hideTip() {
    tip.hidden = true;
    stage.querySelectorAll('.ml-tz-pulse.is-active').forEach(function (el) {
      el.classList.remove('is-active');
    });
  }

  stage.querySelectorAll('.ml-tz-pulse').forEach(function (btn) {
    btn.addEventListener('mouseenter', function () { showTip(btn); });
    btn.addEventListener('focus', function () { showTip(btn); });
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (btn.classList.contains('is-active') && !tip.hidden) hideTip();
      else showTip(btn);
    });
  });

  stage.addEventListener('mouseleave', hideTip);
})();
</script>
@endpush
