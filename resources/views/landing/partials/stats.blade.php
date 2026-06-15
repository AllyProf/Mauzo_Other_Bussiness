<section id="stats" class="stats section light-background">
  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="row gy-4 align-items-center justify-content-between">
      <div class="col-lg-5">
        <img src="{{ asset('gp-assets/img/stats-img.jpg') }}" alt="Team collaboration" class="img-fluid rounded">
      </div>
      <div class="col-lg-6">
        <h3 class="fw-bold fs-2 mb-3">Built for busy counters and demanding owners</h3>
        <p>
          From morning stock checks to evening handovers, {{ $platformName }} keeps cashiers fast and gives owners confidence in every day's numbers.
        </p>
        <div class="row gy-4">
          @foreach([
            ['bi-shop', '120', 'Active Businesses', 'shops & enterprises'],
            ['bi-receipt-cutoff', '85000', 'Sales Processed', 'orders recorded safely'],
            ['bi-headset', '24', 'Hours Support Window', 'local business hours'],
            ['bi-geo-alt', '26', 'Regions Covered', 'across Tanzania'],
          ] as $stat)
          <div class="col-lg-6">
            <div class="stats-item d-flex">
              <i class="bi {{ $stat[0] }} flex-shrink-0"></i>
              <div>
                <span data-purecounter-start="0" data-purecounter-end="{{ $stat[1] }}" data-purecounter-duration="1" class="purecounter"></span>
                <p><strong>{{ $stat[2] }}</strong> <span>{{ $stat[3] }}</span></p>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>
