<section id="contact" class="contact section">
  <div class="container section-title" data-aos="fade-up">
    <h2>Contact Us</h2>
    <p>Call or WhatsApp us for a demo, pricing, or setup help</p>
  </div>

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    <div class="contact-clean-grid">

      <div class="contact-clean-item" data-aos="fade-up" data-aos-delay="100">
        <div class="contact-clean-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="contact-clean-body">
          <h5>Our Location</h5>
          <p>Ben Bella Street, Moshi Municipality<br>
          Opposite High Court of Tanzania<br>
          P.O. Box 20, Moshi – Kilimanjaro &nbsp;·&nbsp; Postcode: 25101</p>
          <span class="contact-clean-note">Near the Kilimanjaro Regional Commissioner's Office</span>
        </div>
      </div>

      <div class="contact-clean-divider"></div>

      <div class="contact-clean-item" data-aos="fade-up" data-aos-delay="200">
        <div class="contact-clean-icon"><i class="bi bi-telephone-fill"></i></div>
        <div class="contact-clean-body">
          <h5>Call Us Now</h5>
          <a href="tel:+255749719998" class="contact-clean-link">+255 749 719 998</a>
          <a href="https://wa.me/255749719998" target="_blank" class="contact-wa-pill">
            <i class="bi bi-whatsapp"></i> WhatsApp
          </a>
        </div>
      </div>

      <div class="contact-clean-divider"></div>

      <div class="contact-clean-item" data-aos="fade-up" data-aos-delay="300">
        <div class="contact-clean-icon"><i class="bi bi-envelope-fill"></i></div>
        <div class="contact-clean-body">
          <h5>Mail Us Now</h5>
          <a href="mailto:emca@emca.tech" class="contact-clean-link">emca@emca.tech</a>
          <span class="contact-clean-note">We reply within 24 hours on business days</span>
        </div>
      </div>

    </div>
  </div>
</section>

@push('styles')
<style>
  .contact-clean-grid {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0;
    border-top: 1px solid rgba(0,0,0,0.08);
    padding-top: 40px;
  }
  .contact-clean-item {
    display: flex;
    align-items: flex-start;
    gap: 18px;
    flex: 1 1 260px;
    min-width: 220px;
    padding: 10px 32px;
  }
  .contact-clean-divider {
    width: 1px;
    background: rgba(0,0,0,0.08);
    align-self: stretch;
    min-height: 100px;
    flex-shrink: 0;
  }
  .contact-clean-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(148,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
  }
  .contact-clean-icon i {
    color: var(--accent-color);
    font-size: 1.1rem;
  }
  .contact-clean-body h5 {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--accent-color);
    margin-bottom: 8px;
  }
  .contact-clean-body p {
    font-size: 0.93rem;
    color: #444;
    line-height: 1.7;
    margin: 0 0 6px;
  }
  .contact-clean-link {
    display: block;
    font-size: 1.05rem;
    font-weight: 700;
    color: #222;
    text-decoration: none;
    margin-bottom: 10px;
    transition: color .2s;
  }
  .contact-clean-link:hover { color: var(--accent-color); }
  .contact-wa-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #25D366;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 20px;
    text-decoration: none;
    transition: background .2s;
  }
  .contact-wa-pill:hover { background: #1ebe59; color: #fff; }
  .contact-wa-pill i { font-size: 1rem; }
  .contact-clean-note {
    display: block;
    font-size: 0.8rem;
    color: #999;
    margin-top: 4px;
    font-style: italic;
  }
  @media (max-width: 768px) {
    .contact-clean-divider { display: none; }
    .contact-clean-item {
      padding: 16px 8px;
      border-bottom: 1px solid rgba(0,0,0,0.07);
    }
    .contact-clean-item:last-child { border-bottom: none; }
  }
</style>
@endpush
