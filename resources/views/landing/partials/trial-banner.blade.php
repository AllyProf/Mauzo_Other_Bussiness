@if($registrationOpen ?? true)
<section id="trial-banner" class="ml-trial-banner" aria-label="{{ __('landing.trial_banner.aria') }}">
  <div class="container">
    <div class="ml-trial-banner__row">
      <p class="ml-trial-banner__text">
        {!! __('landing.trial_banner.title_html', ['days' => $trialDays]) !!}
      </p>
      <a class="ml-trial-banner__btn" href="{{ route('register.business') }}">
        {{ __('landing.trial_banner.button') }}
      </a>
    </div>
  </div>
</section>
@endif
