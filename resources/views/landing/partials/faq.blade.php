<section id="faq" class="faq section light-background">
  <div class="container section-title" data-aos="fade-up">
    <h2>{{ __('landing.faq.title') }}</h2>
    <p>{{ __('landing.faq.subtitle') }}</p>
  </div>

  <div class="container">
    <div class="row gy-4 align-items-center">
      <div class="col-lg-7 order-2 order-lg-1" data-aos="fade-up" data-aos-delay="200">
        <div class="faq-container">
          @foreach(__('landing.faq.items') as $i => $item)
          <div class="faq-item {{ $i === 0 ? 'faq-active' : '' }}">
            <h3>{{ $item['q'] }}</h3>
            <div class="faq-content">
              <p>{{ $item['a'] }}</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>
          @endforeach
        </div>
      </div>
      <div class="col-lg-5 order-1 order-lg-2" data-aos="fade-up" data-aos-delay="100">
        <img src="{{ asset('gp-assets/img/faq.png') }}" class="img-fluid rounded shadow" alt="{{ __('landing.faq.image_alt') }}">
      </div>
    </div>
  </div>
</section>

@push('styles')
<style>
  .faq .faq-container { margin-top: 15px; }
  .faq .faq-item {
    background-color: #fff; position: relative; padding: 20px; margin-bottom: 20px;
    border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 8px; cursor: pointer; transition: 0.3s;
  }
  .faq .faq-item:last-child { margin-bottom: 0; }
  .faq .faq-item h3 {
    font-weight: 600; font-size: 1.1rem; line-height: 24px; margin: 0; padding-right: 30px;
    color: var(--heading-color); transition: 0.3s;
  }
  .faq .faq-content { display: none; padding-top: 15px; }
  .faq .faq-content p { margin-bottom: 0; font-size: 0.95rem; color: #555; }
  .faq .faq-toggle {
    position: absolute; top: 20px; right: 20px; font-size: 16px; line-height: 0;
    transition: 0.3s; color: var(--accent-color);
  }
  .faq .faq-active { border-color: var(--accent-color); }
  .faq .faq-active h3 { color: var(--accent-color); }
  .faq .faq-active .faq-content { display: block; }
  .faq .faq-active .faq-toggle { transform: rotate(90deg); }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
      item.addEventListener('click', () => {
        const isActive = item.classList.contains('faq-active');
        faqItems.forEach(i => {
          i.classList.remove('faq-active');
          const content = i.querySelector('.faq-content');
          if(content) content.style.display = 'none';
        });
        if (!isActive) {
          item.classList.add('faq-active');
          const content = item.querySelector('.faq-content');
          if(content) content.style.display = 'block';
        }
      });
    });
  });
</script>
@endpush
