<section id="services" class="services section light-background">

  <div class="container">
    <div class="row gy-4">
      @foreach([
        ['bi-shop', 'Retail & spare parts POS', 'Grid or list selling, barcodes, packaging, and category filters for high-volume catalogs.'],
        ['bi-scissors', 'Service businesses', 'Salon, printing, and custom service lines with consumables and service invoicing.'],
        ['bi-truck', 'Receivings & suppliers', 'Record incoming stock, costs, and supplier history without breaking inventory totals.'],
        ['bi-people', 'Customers & debt', 'Registered customers, walk-ins, debt tracking, and collections on later shifts.'],
        ['bi-diagram-3', 'Multi-branch control', 'Separate branches, branch switching, and business-wide owner visibility.'],
        ['bi-bell', 'SMS & notifications', 'Automated messages for staff, customers, and platform alerts where enabled.'],
      ] as $i => $service)
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ 100 + ($i * 50) }}">
        <div class="service-item position-relative h-100">
          <div class="icon"><i class="bi {{ $service[0] }}"></i></div>
          <h3>{{ $service[1] }}</h3>
          <p>{{ $service[2] }}</p>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>
