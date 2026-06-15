<section id="testimonials" class="testimonials section dark-background">
  <img src="{{ asset('gp-assets/img/testimonials-bg.jpg') }}" class="testimonials-bg" alt="">

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="swiper init-swiper">
      <script type="application/json" class="swiper-config">
        {
          "loop": true,
          "speed": 600,
          "autoplay": { "delay": 5000 },
          "slidesPerView": "auto",
          "pagination": {
            "el": ".swiper-pagination",
            "type": "bullets",
            "clickable": true
          }
        }
      </script>
      <div class="swiper-wrapper">
        @foreach([
          ['testimonials-1.jpg', 'Amina Hassan', 'Spare Parts Shop, Dar es Salaam', 'We finally see real stock levels every shift. Debt collections from yesterday show clearly on handover — no more guessing at closing time.'],
          ['testimonials-2.jpg', 'Joseph Mwangi', 'Pharmacy Owner, Arusha', 'My cashiers learned the POS in one day. Owner reports tell me profit and circulation without waiting for Excel at night.'],
          ['testimonials-3.jpg', 'Neema Komba', 'Salon & Services, Mwanza', 'Service sales and retail stock in one system saved us from using two notebooks and a calculator.'],
        ] as $item)
        <div class="swiper-slide">
          <div class="testimonial-item">
            <img src="{{ asset('gp-assets/img/testimonials/'.$item[0]) }}" class="testimonial-img" alt="">
            <h3>{{ $item[1] }}</h3>
            <h4>{{ $item[2] }}</h4>
            <div class="stars">
              @for($s = 0; $s < 5; $s++)<i class="bi bi-star-fill"></i>@endfor
            </div>
            <p>
              <i class="bi bi-quote quote-icon-left"></i>
              <span>{{ $item[3] }}</span>
              <i class="bi bi-quote quote-icon-right"></i>
            </p>
          </div>
        </div>
        @endforeach
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </div>
</section>
