<section id="features" class="features section">
  <div class="container">
    <div class="row gy-4">
      <div class="features-image col-lg-6" data-aos="fade-up" data-aos-delay="100">
        <img src="{{ asset('gp-assets/img/features-bg.jpg') }}" class="img-fluid rounded" alt="Business analytics">
      </div>
      <div class="col-lg-6">
        @foreach([
          ['bi-receipt', 'Smart point of sale', 'Quick item search, categories, packaging units, customer picker, and mobile-friendly checkout.'],
          ['bi-boxes', 'Real-time inventory', 'Stock moves with sales and receivings. Shift opening counts keep quantities honest during the day.'],
          ['bi-safe', 'Shift & day closing', 'Cash handover, prior-shift collections, expenses, and profit/circulation summaries for owners.'],
          ['bi-bar-chart-line', 'Owner dashboards', 'Daily sales, targets, branch performance, and exportable reports without spreadsheet chaos.'],
        ] as $i => $feature)
        <div class="features-item d-flex ps-0 ps-lg-3 {{ $i ? 'mt-5' : 'pt-4 pt-lg-0' }}" data-aos="fade-up" data-aos-delay="{{ 200 + ($i * 100) }}">
          <i class="bi {{ $feature[0] }} flex-shrink-0"></i>
          <div>
            <h4>{{ $feature[1] }}</h4>
            <p>{{ $feature[2] }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
