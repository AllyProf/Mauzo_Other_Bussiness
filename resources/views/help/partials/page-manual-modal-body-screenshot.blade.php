@php
  $manual = $manual ?? [];
  $callouts = $manual['callouts'] ?? [];
  $screenshot = $manual['screenshot'] ?? null;
  $screenshotPanels = $manual['screenshot_panels'] ?? null;
  $hasPanels = ! empty($screenshotPanels) && is_array($screenshotPanels);
  $title = $manual['title'] ?? '';
@endphp
<div class="page-manual-modal__stack page-manual-modal__stack--vertical">
  @if($hasPanels)
    <div class="page-manual-modal__steps-interleaved px-3 px-md-4 py-3">
      <div class="page-manual-modal__desc-head mb-2">
        <span class="page-manual-modal__desc-badge">{{ __('page_manual.description_heading') }}</span>
      </div>
      <p class="page-manual-modal__intro mb-4">
        {{ __('page_manual.intro_window', ['screen' => $title]) }}
      </p>
      @foreach($callouts as $index => $c)
        <section class="page-manual-modal__step-section" aria-labelledby="page-manual-step-{{ $c['n'] ?? ($index + 1) }}">
          <div class="page-manual-modal__step-block mb-0">
            <div class="page-manual-modal__step-head">
              <span class="page-manual-modal__step-badge">{{ $c['n'] ?? ($index + 1) }}</span>
              <strong class="page-manual-modal__step-label" id="page-manual-step-{{ $c['n'] ?? ($index + 1) }}">{{ $c['title'] ?? '' }}</strong>
            </div>
            @if(!empty($c['text']))
              <p class="page-manual-modal__step-text mb-0 mt-2">{{ $c['text'] }}</p>
            @endif
            @if(!empty($c['details']) && is_array($c['details']))
              <ul class="page-manual-modal__step-details mb-0 mt-2">
                @foreach($c['details'] as $detail)
                  <li>{{ $detail }}</li>
                @endforeach
              </ul>
            @endif
          </div>
          @if(isset($screenshotPanels[$index]))
            <div class="page-manual-modal__step-figure">
              @include('help.partials.page-manual-screenshot', [
                'screenshot' => $screenshotPanels[$index]['screenshot'] ?? null,
                'callouts' => $screenshotPanels[$index]['callouts'] ?? [],
              ])
            </div>
          @endif
        </section>
      @endforeach
    </div>
  @else
    @if($screenshot)
      <div class="page-manual-modal__figures px-3 px-md-4 pt-3 pb-2">
        @include('help.partials.page-manual-screenshot', [
          'screenshot' => $screenshot,
          'callouts' => $callouts,
        ])
      </div>
    @endif
    <div class="page-manual-modal__steps-column px-3 px-md-4 py-3">
      <div class="page-manual-modal__desc-head mb-2">
        <span class="page-manual-modal__desc-badge">{{ __('page_manual.description_heading') }}</span>
      </div>
      <p class="page-manual-modal__intro mb-3">
        {{ __('page_manual.intro_window', ['screen' => $title]) }}
      </p>
      <div class="page-manual-modal__steps">
        @foreach($callouts as $c)
          <div class="page-manual-modal__step-block mb-2">
            <div class="page-manual-modal__step-head">
              <span class="page-manual-modal__step-badge">{{ $c['n'] ?? '' }}</span>
              <strong class="page-manual-modal__step-label">{{ $c['title'] ?? '' }}</strong>
            </div>
            @if(!empty($c['text']))
              <p class="page-manual-modal__step-text mb-0">{{ $c['text'] }}</p>
            @endif
            @if(!empty($c['details']) && is_array($c['details']))
              <ul class="page-manual-modal__step-details mb-0 mt-2">
                @foreach($c['details'] as $detail)
                  <li>{{ $detail }}</li>
                @endforeach
              </ul>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>
