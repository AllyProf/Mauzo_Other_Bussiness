<section id="pricing" class="services section">
  <div class="container section-title" data-aos="fade-up">
    <h2>Pricing</h2>
    <p>Transparent plans for growing Tanzanian businesses</p>
  </div>

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    @if($plans->isEmpty())
      <div class="text-center py-4">
        <p class="mb-3">Contact us for current pricing and a tailored package for your business size.</p>
        <a href="#contact" class="btn-getstarted px-4 py-2">Talk to Sales</a>
      </div>
    @else
      <div class="row gy-4 justify-content-center">
        @foreach($plans as $index => $plan)
        @php
          $allLabels = collect(config('plan_features.groups', []))->flatMap(fn($g) => $g)->all();
          $enabledKeys = $plan->enabledFeatures();
          $featureLabels = collect($enabledKeys)->map(fn($k) => $allLabels[$k] ?? ucfirst(str_replace('_', ' ', $k)))->values();
          $preview = $featureLabels->take(4);
          $remaining = $featureLabels->count() - 4;
          $modalId = 'planModal'.$plan->id;
        @endphp
        <div class="col-lg-4 col-md-6">
          <div class="pricing-card h-100 {{ $index === 1 ? 'featured' : '' }}">
            @if($index === 1)
              <span class="plan-badge">Popular</span>
            @endif
            @if($trialDays > 0 && $index === 0)
              <span class="plan-badge" style="background:#28a745;">{{ $trialDays }}-day trial</span>
            @endif
            <h4>{{ $plan->name }}</h4>
            <ul>
              @forelse($preview as $label)
                <li><i class="bi bi-check-circle"></i> {{ $label }}</li>
              @empty
                <li><i class="bi bi-check-circle"></i> Core POS &amp; Inventory</li>
              @endforelse
            </ul>
            @if($remaining > 0)
              <button class="view-all-features-btn" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                <i class="bi bi-grid"></i> View all {{ $featureLabels->count() }} features
              </button>
            @endif
            @if($registrationOpen)
              <a href="{{ route('register.business') }}" class="pricing-cta-btn">Register Now</a>
            @endif
            <div class="pricing-contact-links">
              <a href="tel:0616775800" title="Call Us"><i class="bi bi-telephone-fill"></i> Call</a>
              <span class="pricing-contact-sep">|</span>
              <a href="https://wa.me/255616775800" target="_blank" title="WhatsApp Us"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            </div>
          </div>
        </div>

        {{-- Features Modal --}}
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content plan-modal-content">
              <div class="modal-header plan-modal-header">
                <h5 class="modal-title" id="{{ $modalId }}Label">
                  <i class="bi bi-check2-all"></i> {{ $plan->name }} — All Features
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                @foreach(config('plan_features.groups', []) as $groupName => $groupFeatures)
                  @php
                    $groupEnabled = collect($groupFeatures)->filter(fn($label, $key) => in_array($key, $enabledKeys, true));
                  @endphp
                  @if($groupEnabled->isNotEmpty())
                    <p class="plan-modal-group">{{ $groupName }}</p>
                    <ul class="plan-modal-list">
                      @foreach($groupEnabled as $key => $label)
                        <li><i class="bi bi-check-circle-fill"></i> {{ $label }}</li>
                      @endforeach
                    </ul>
                  @endif
                @endforeach
              </div>
              <div class="modal-footer plan-modal-footer">
                @if($registrationOpen)
                  <a href="{{ route('register.business') }}" class="pricing-cta-btn">Register Now</a>
                @endif
                <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    @endif
  </div>
</section>

@push('styles')
<style>
  .pricing-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    position: relative;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .pricing-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(148,0,0,0.12);
  }
  .pricing-card.featured { border: 2px solid var(--accent-color); }
  .pricing-card .plan-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--accent-color);
    color: #fff;
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
  }
  .pricing-card h4 { color: var(--accent-color); font-weight: 700; margin-bottom: 12px; font-size: 1.3rem; }
  .pricing-card ul { list-style: none; padding: 0; margin: 0 0 12px; text-align: left; width: 100%; }
  .pricing-card ul li { padding: 7px 0; border-bottom: 1px solid rgba(0,0,0,0.06); font-size: 0.92rem; }
  .pricing-card ul li i { color: var(--accent-color); margin-right: 8px; }

  .view-all-features-btn {
    background: none;
    border: none;
    color: var(--accent-color);
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 0 12px;
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: opacity .2s;
  }
  .view-all-features-btn:hover { opacity: 0.75; }
  .view-all-features-btn i { margin-right: 4px; }

  .pricing-cta-btn {
    font-family: var(--heading-font);
    font-weight: 500;
    font-size: 16px;
    letter-spacing: 1px;
    display: inline-block;
    padding: 10px 35px;
    border-radius: 5px;
    transition: 0.5s;
    margin-top: 16px;
    border: 2px solid var(--accent-color);
    color: #fff;
    background: var(--accent-color);
    text-decoration: none;
  }
  .pricing-cta-btn:hover {
    background: transparent;
    color: var(--accent-color);
  }
  .pricing-contact-links {
    margin-top: 14px;
    font-size: 0.84rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .pricing-contact-links a {
    color: #555;
    text-decoration: none;
    transition: color .2s;
  }
  .pricing-contact-links a:hover { color: var(--accent-color); }
  .pricing-contact-links a .bi-whatsapp { color: #25D366; }
  .pricing-contact-links a:hover .bi-whatsapp { color: #1ebe59; }
  .pricing-contact-sep { color: #ccc; }

  /* Plan Modal */
  .plan-modal-content {
    border: none;
    border-radius: 14px;
    overflow: hidden;
  }
  .plan-modal-header {
    background: var(--accent-color);
    color: #fff;
    border: none;
    padding: 18px 24px;
  }
  .plan-modal-header .modal-title { font-weight: 700; font-size: 1.1rem; }
  .plan-modal-header .modal-title i { margin-right: 8px; }
  .plan-modal-group {
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #888;
    margin: 16px 0 6px;
  }
  .plan-modal-list {
    list-style: none;
    padding: 0;
    margin: 0 0 8px;
  }
  .plan-modal-list li {
    padding: 7px 0;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    font-size: 0.93rem;
  }
  .plan-modal-list li i { color: var(--accent-color); margin-right: 8px; }
  .plan-modal-footer {
    border-top: 1px solid rgba(0,0,0,0.08);
    padding: 16px 24px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    align-items: center;
  }
  .btn-modal-close {
    background: #f3f3f3;
    border: none;
    border-radius: 6px;
    padding: 8px 20px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background .2s;
  }
  .btn-modal-close:hover { background: #e0e0e0; }
</style>
@endpush
