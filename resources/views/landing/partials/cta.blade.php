<section id="call-to-action" class="call-to-action section dark-background">
  <img src="{{ asset('gp-assets/img/cta-bg.jpg') }}" alt="">
  <div class="container">
    <div class="row justify-content-center" data-aos="zoom-in" data-aos-delay="100">
      <div class="col-xl-10">
        <div class="text-center">
          <h3>Ready to modernize your shop?</h3>
          <p>
            Join businesses using {{ $platformName }} to sell faster, control stock, and close every day with accurate cash and profit reports.
            @if($registrationOpen)
              Register today and start your {{ $trialDays }}-day trial after approval.
            @endif
          </p>
          @if($registrationOpen)
            <a class="cta-btn me-2" href="{{ route('register.business') }}">Register Now</a>
          @endif
          <a class="cta-btn" href="#contact">Request a Demo</a>
        </div>
      </div>
    </div>
  </div>
</section>
