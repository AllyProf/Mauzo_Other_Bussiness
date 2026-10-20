@php
  $manual = $manual ?? page_manual_for_current_route();
  $hasManual = is_array($manual) && ! empty($manual['callouts']);
  $setupSteps = system_guide_setup_steps_for_manual();
  $appName = config('app.name', 'SpareParts POS');
  $logoUrl = asset('gp-assets/img/logo.png');
@endphp
@if(tenant_system_guide_visible())
<div class="modal fade page-manual-modal" id="pageManualModal" tabindex="-1" role="dialog" aria-labelledby="pageManualModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered page-manual-modal__dialog page-manual-modal__dialog--zoomable" role="document">
    <div class="page-manual-modal-zoom js-page-manual-modal-zoom" data-zoom="1">
      <div class="page-manual-modal-zoom__toolbar" role="toolbar" aria-label="{{ __('page_manual.zoom_toolbar') }}">
        <button type="button" class="page-manual-modal-zoom__btn js-page-manual-zoom-out" title="{{ __('page_manual.zoom_out') }}" aria-label="{{ __('page_manual.zoom_out') }}">
          <i class="fa fa-search-minus" aria-hidden="true"></i>
        </button>
        <span class="page-manual-modal-zoom__pct js-page-manual-zoom-label" aria-live="polite">100%</span>
        <button type="button" class="page-manual-modal-zoom__btn js-page-manual-zoom-in" title="{{ __('page_manual.zoom_in') }}" aria-label="{{ __('page_manual.zoom_in') }}">
          <i class="fa fa-search-plus" aria-hidden="true"></i>
        </button>
        <button type="button" class="page-manual-modal-zoom__btn js-page-manual-zoom-reset" title="{{ __('page_manual.zoom_reset') }}" aria-label="{{ __('page_manual.zoom_reset') }}">
          <i class="fa fa-undo" aria-hidden="true"></i>
          <span class="d-none d-sm-inline">{{ __('page_manual.zoom_reset') }}</span>
        </button>
      </div>
      <div class="page-manual-modal-zoom__viewport js-page-manual-modal-zoom-viewport">
        <div class="page-manual-modal-zoom__sizer js-page-manual-modal-zoom-sizer">
          <div class="page-manual-modal-zoom__inner js-page-manual-modal-zoom-inner">
    @php
      $manualHasScreenshot = $hasManual && (! empty($manual['screenshot']) || ! empty($manual['screenshot_panels']));
    @endphp
    <div class="modal-content page-manual-modal__content @if($manualHasScreenshot) page-manual-modal--has-screenshot @endif">
      @if($hasManual)
        <div class="page-manual-modal__header-row">
          <div class="page-manual-modal__header-dark">
            <span id="pageManualModalTitle">{{ $manual['title'] }}</span>
          </div>
          <div class="page-manual-modal__header-brand">
            <span class="page-manual-modal__brand-wrap">
              <img src="{{ $logoUrl }}" alt="" class="page-manual-modal__brand-logo" width="28" height="28">
              <span class="page-manual-modal__brand-text">{{ $appName }}</span>
            </span>
            <button type="button" class="close page-manual-modal__close" data-dismiss="modal" aria-label="{{ __('page_manual.close') }}">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class="page-manual-modal__hero-box">
          <div class="page-manual-modal__hero-inner">
            <p class="page-manual-modal__menu-path mb-1">{{ $manual['menu'] }}</p>
            <h2 class="page-manual-modal__screen-title mb-0">{{ $manual['title'] }}</h2>
          </div>
          <div class="page-manual-modal__hero-icon" aria-hidden="true">
            <i class="fa fa-book"></i>
          </div>
        </div>
        <div class="modal-body page-manual-modal__body page-manual-printable p-0">
          @php($useScreenshot = !empty($manual['screenshot']) || !empty($manual['screenshot_panels']))
          @if($useScreenshot)
            @include('help.partials.page-manual-modal-body-screenshot', ['manual' => $manual])
          @else
          <div class="row no-gutters page-manual-modal__split">
            <div class="col-lg-6 page-manual-modal__left">
              @include('help.partials.page-manual-wireframe', [
                'wireframe' => $manual['wireframe'],
                'screenshot' => null,
                'screenshot_panels' => null,
                'callouts' => $manual['callouts'],
              ])
            </div>
            <div class="col-lg-6 page-manual-modal__right">
              <div class="page-manual-modal__desc-head mb-2">
                <span class="page-manual-modal__desc-badge">{{ __('page_manual.description_heading') }}</span>
              </div>
              <p class="page-manual-modal__screen-ref mb-2">
                <span class="page-manual-modal__diamond" aria-hidden="true">◆</span> {{ $manual['title'] }}
              </p>
              <p class="page-manual-modal__intro mb-3">
                {{ __('page_manual.intro_window', ['screen' => $manual['title']]) }}
              </p>
              <div class="page-manual-modal__steps">
                @foreach($manual['callouts'] as $c)
                  <div class="page-manual-modal__step-block mb-2">
                    <div class="page-manual-modal__step-head">
                      <span class="page-manual-modal__step-badge">{{ $c['n'] }}</span>
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
          </div>
          @endif
          <div class="page-manual-modal__footer-meta">
            <hr class="page-manual-modal__footer-rule">
            <div class="page-manual-modal__footer-logo-row">
              <img src="{{ $logoUrl }}" alt="" width="24" height="24">
              <span>{{ $appName }}</span>
            </div>
          </div>
        </div>
        <div class="page-manual-modal__footer">
          <div class="page-manual-modal__footer-spacer"></div>
          <div class="page-manual-modal__footer-actions">
            <button type="button" class="btn btn-light btn-sm page-manual-modal__btn" id="pageManualDownloadBtn">
              {{ __('page_manual.download') }}
            </button>
            <button type="button" class="btn btn-light btn-sm page-manual-modal__btn page-manual-modal__btn--close" data-dismiss="modal">
              {{ __('page_manual.close') }}
            </button>
          </div>
        </div>
      @else
        <div class="page-manual-modal__header-row">
          <div class="page-manual-modal__header-dark">
            <span>{{ __('page_manual.description_heading') }}</span>
          </div>
          <div class="page-manual-modal__header-brand">
            <span class="page-manual-modal__brand-wrap">
              <img src="{{ $logoUrl }}" alt="" class="page-manual-modal__brand-logo" width="28" height="28">
              <span class="page-manual-modal__brand-text">{{ $appName }}</span>
            </span>
            <button type="button" class="close page-manual-modal__close" data-dismiss="modal" aria-label="{{ __('page_manual.close') }}">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class="modal-body p-4">
          <p class="mb-3">{{ __('page_manual.no_screen_manual') }}</p>
          @if(count($setupSteps) > 0)
            <p class="font-weight-bold mb-2">{{ __('page_manual.setup_guide_heading') }}</p>
            <ol class="page-manual-modal__setup-list pl-3 mb-3">
              @foreach($setupSteps as $s)
                <li class="mb-2">
                  <strong>{{ __('help.getting_started.step') }} {{ $s['number'] }} — {{ $s['title'] }}</strong>
                  <span class="d-block small text-muted">{{ $s['menu'] }}</span>
                  @if($s['url'])
                    <a href="{{ $s['url'] }}" class="small">{{ __('help.getting_started.open_screen') }}</a>
                  @endif
                </li>
              @endforeach
            </ol>
          @endif
          <a href="{{ route('help.getting-started') }}" class="btn btn-primary btn-sm">{{ __('page_manual.full_guide') }}</a>
        </div>
        <div class="page-manual-modal__footer">
          <div class="page-manual-modal__footer-spacer"></div>
          <div class="page-manual-modal__footer-actions">
            <button type="button" class="btn btn-light btn-sm page-manual-modal__btn page-manual-modal__btn--close" data-dismiss="modal">
              {{ __('page_manual.close') }}
            </button>
          </div>
        </div>
      @endif
    </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endif
