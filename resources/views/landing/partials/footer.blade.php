<footer id="footer" class="footer dark-background">
  <div class="footer-top">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-6 footer-about">
          <a href="{{ route('landing.index') }}" class="logo d-flex align-items-center">
            <span class="sitename">{{ $platformName }}</span>
          </a>
          <div class="footer-contact pt-3">
            <p>Tanzania</p>
            @if(filled($supportPhone ?? null))
              <p class="mt-3"><strong>Phone:</strong> <span>{{ $supportPhone }}</span></p>
            @endif
            @if(filled($supportEmail ?? null))
              <p><strong>Email:</strong> <span>{{ $supportEmail }}</span></p>
            @endif
          </div>
        </div>

        <div class="col-lg-2 col-md-3 footer-links">
          <h4>Explore</h4>
          <ul>
            <li><i class="bi bi-chevron-right"></i> <a href="#hero">Home</a></li>
            <li><i class="bi bi-chevron-right"></i> <a href="#about">About</a></li>
            <li><i class="bi bi-chevron-right"></i> <a href="#features">Features</a></li>
            <li><i class="bi bi-chevron-right"></i> <a href="#pricing">Pricing</a></li>
          </ul>
        </div>

        <div class="col-lg-2 col-md-3 footer-links">
          <h4>Account</h4>
          <ul>
            <li><i class="bi bi-chevron-right"></i> <a href="{{ route('login') }}">Sign In</a></li>
            @if($registrationOpen ?? true)
              <li><i class="bi bi-chevron-right"></i> <a href="{{ route('register.business') }}">Register</a></li>
            @endif
            <li><i class="bi bi-chevron-right"></i> <a href="#contact">Request Demo</a></li>
          </ul>
        </div>

        <div class="col-lg-4 col-md-12 footer-newsletter">
          <h4>Ready to grow?</h4>
          <p>Start with a {{ $trialDays }}-day trial after approval, or book a walkthrough with our team.</p>
          @if($registrationOpen ?? true)
            <a href="{{ route('register.business') }}" class="btn-getstarted d-inline-block mt-2">Register Your Business</a>
          @else
            <a href="#contact" class="btn-getstarted d-inline-block mt-2">Contact Us</a>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="copyright">
    <div class="container text-center">
      <p>&copy; {{ date('Y') }} <strong class="px-1 sitename">{{ $platformName }}</strong>. All rights reserved.</p>
    </div>
  </div>
</footer>
