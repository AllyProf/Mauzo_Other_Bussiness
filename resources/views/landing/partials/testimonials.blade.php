<section id="testimonials" class="testimonials section dark-background">
  <img src="{{ asset('gp-assets/img/testimonials-bg.jpg') }}" class="testimonials-bg" alt="">

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="swiper init-swiper">
      <script type="application/json" class="swiper-config">
        {
          "loop": true,
          "speed": 600,
          "autoplay": { "delay": 5000 },
          "slidesPerView": 1,
          "spaceBetween": 30,
          "pagination": {
            "el": ".swiper-pagination",
            "type": "bullets",
            "clickable": true
          }
        }
      </script>
      <div class="swiper-wrapper">
        @foreach(__('landing.testimonials.items') as $item)
        <div class="swiper-slide">
          <div class="testimonial-item">
            <div class="testimonial-icon"><i class="bi bi-person-circle"></i></div>
            <h3>{{ $item['name'] }}</h3>
            <h4>{{ $item['role'] }}</h4>
            <div class="stars">
              @for($s = 0; $s < 5; $s++)<i class="bi bi-star-fill"></i>@endfor
            </div>
            <p>
              <i class="bi bi-quote quote-icon-left"></i>
              <span>{{ $item['quote'] }}</span>
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

@push('styles')
<style>
  .testimonial-icon {
    width: 90px; height: 90px; border-radius: 50%; margin: -40px auto 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.1); border: 6px solid rgba(255, 255, 255, 0.15);
  }
  .testimonial-icon i { font-size: 50px; color: #fff; }
</style>
@endpush
