<section id="faq" class="faq section light-background">
  <div class="container section-title" data-aos="fade-up">
    <h2>Frequently Asked Questions</h2>
    <p>Everything you need to know about getting started with Mauzo Link</p>
  </div>

  <div class="container">
    <div class="row gy-4 align-items-center">
      <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
        <img src="{{ asset('gp-assets/img/faq.png') }}" class="img-fluid rounded shadow" alt="Customer Support">
      </div>
      <div class="col-lg-7" data-aos="fade-up" data-aos-delay="200">
        <div class="faq-container">

          <div class="faq-item faq-active">
            <h3>Do I need a continuous internet connection to sell?</h3>
            <div class="faq-content">
              <p>Mauzo Link is a secure, cloud-based platform. While you need an internet connection to sync data and close shifts, our interface is highly optimized to consume very little data, making it perfectly stable even on standard mobile network hotspots.</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>

          <div class="faq-item">
            <h3>Can I use this on my phone, or do I need a computer?</h3>
            <div class="faq-content">
              <p>You can use Mauzo Link on almost any device! Our platform is fully responsive and works beautifully on smartphones, tablets, laptops, and dedicated point-of-sale hardware screens.</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>

          <div class="faq-item">
            <h3>Does it support barcode scanners and receipt printers?</h3>
            <div class="faq-content">
              <p>Yes. The system is perfectly compatible with standard USB or Bluetooth barcode scanners, as well as standard 58mm and 80mm thermal receipt printers for quick customer checkouts.</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>

          <div class="faq-item">
            <h3>What happens if a cashier makes a mistake?</h3>
            <div class="faq-content">
              <p>Cashiers can freely void or remove items before a checkout is finalized. However, once a receipt is closed and the sale is recorded, only authorized users (like managers or owners) can process returns or edits. This ensures your physical cash always matches the system.</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>

          <div class="faq-item">
            <h3>Is my business data secure if my device breaks?</h3>
            <div class="faq-content">
              <p>Absolutely. Because Mauzo Link is cloud-based, your data is backed up in real-time. If a tablet breaks, gets lost, or stolen, you simply log in on a new device and all your stock, sales, and debt records will be exactly where you left them.</p>
            </div>
            <i class="faq-toggle bi bi-chevron-right"></i>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

@push('styles')
<style>
  .faq .faq-container {
    margin-top: 15px;
  }
  .faq .faq-item {
    background-color: #fff;
    position: relative;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
  }
  .faq .faq-item:last-child {
    margin-bottom: 0;
  }
  .faq .faq-item h3 {
    font-weight: 600;
    font-size: 1.1rem;
    line-height: 24px;
    margin: 0;
    padding-right: 30px;
    color: var(--heading-color);
    transition: 0.3s;
  }
  .faq .faq-content {
    display: none;
    padding-top: 15px;
  }
  .faq .faq-content p {
    margin-bottom: 0;
    font-size: 0.95rem;
    color: #555;
  }
  .faq .faq-toggle {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 16px;
    line-height: 0;
    transition: 0.3s;
    color: var(--accent-color);
  }
  .faq .faq-active {
    border-color: var(--accent-color);
  }
  .faq .faq-active h3 {
    color: var(--accent-color);
  }
  .faq .faq-active .faq-content {
    display: block;
  }
  .faq .faq-active .faq-toggle {
    transform: rotate(90deg);
  }
</style>
@endpush

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
      item.addEventListener('click', () => {
        const isActive = item.classList.contains('faq-active');
        // Close all other items
        faqItems.forEach(i => {
          i.classList.remove('faq-active');
          const content = i.querySelector('.faq-content');
          if(content) content.style.display = 'none';
        });
        // Toggle current item
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
