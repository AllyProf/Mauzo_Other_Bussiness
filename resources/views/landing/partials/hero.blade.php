<section id="hero" class="hero section dark-background">
  <img src="{{ asset('gp-assets/img/hero-bg.jpg') }}" alt="" data-aos="fade-in">

  <div class="container">
    <div class="row justify-content-center text-center" data-aos="fade-up" data-aos-delay="100">
      <div class="col-xl-8 col-lg-10">
        <h2>Run Your Business Smarter With {{ $platformName }}<span>.</span></h2>
        <p class="lead">Point of sale, inventory, shifts, and daily reports — built for shops, spare parts, pharmacies, and multi-branch businesses in Tanzania.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
          @if($registrationOpen)
            <a href="{{ route('register.business') }}" class="btn-getstarted px-4 py-2">Start Free Trial</a>
          @endif
          <a href="#contact" class="cta-btn px-4 py-2">Book a Demo</a>
        </div>
      </div>
    </div>

    <div class="row gy-4 mt-5 justify-content-center" data-aos="fade-up" data-aos-delay="200">
      @foreach([
        ['bi-cart-check', 'Fast POS', '#services'],
        ['bi-box-seam', 'Stock Control', '#features'],
        ['bi-clock-history', 'Shift Closing', '#features'],
        ['bi-graph-up-arrow', 'Owner Reports', '#features'],
        ['bi-building', 'Multi-Branch', '#services'],
      ] as $i => $box)
      <div class="col-xl-2 col-md-4 col-6" data-aos="fade-up" data-aos-delay="{{ 300 + ($i * 100) }}">
        <div class="icon-box">
          <i class="bi {{ $box[0] }}"></i>
          <h3><a href="{{ $box[2] }}">{{ $box[1] }}</a></h3>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>
