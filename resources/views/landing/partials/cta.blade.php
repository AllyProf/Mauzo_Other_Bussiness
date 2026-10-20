<section id="call-to-action" class="call-to-action section dark-background">
  <img src="{{ asset('gp-assets/img/cta-bg.jpg') }}" alt="">
  <div class="container">
    <div class="row justify-content-center" data-aos="zoom-in" data-aos-delay="100">
      <div class="col-xl-10">
        <div class="text-center">
          <h3>{{ __('landing.cta.title') }}</h3>
          <p>
            {{ __('landing.cta.body', ['platform' => $platformName ?? config('app.name')]) }}
            @if($registrationOpen)
              {{ ' '.__('landing.cta.trial_note', ['days' => $trialDays]) }}
            @endif
          </p>
          @if($registrationOpen)
            <a class="cta-btn me-2" href="{{ route('register.business') }}">{{ __('landing.cta.register') }}</a>
          @endif
          <a class="cta-btn" href="#contact">{{ __('landing.cta.demo') }}</a>
        </div>
      </div>
    </div>
  </div>
</section>
