@extends('public-site.layout')

@section('title', ($content['title'] ?? ucfirst($page)).' — '.$business->name)

@section('content')
<section class="section" style="padding-top: 120px;">
  <div class="container section-title" data-aos="fade-up">
    <h2>{{ $content['title'] ?? ucfirst($page) }}</h2>
    @if(!empty($content['body']) && $page !== 'contact')
      <p>{{ \Illuminate\Support\Str::limit(strip_tags($content['body']), 160) }}</p>
    @endif
  </div>

  <div class="container" data-aos="fade-up" data-aos-delay="100">

    @if($page === 'about')
      <div class="row justify-content-center mb-4">
        <div class="col-lg-8 content text-center">
          <p style="white-space:pre-line;" class="mb-0">{{ $content['body'] ?? '' }}</p>
        </div>
      </div>
      <div class="row gy-4">
        @for($i = 1; $i <= 3; $i++)
          @if(!empty($content['highlight_'.$i.'_title']))
            <div class="col-md-4">
              <div class="features-item text-center p-4 h-100" style="background:#f8f9fa;border-radius:8px;">
                <i class="bi bi-star-fill d-block mb-3" style="font-size:1.75rem;color:var(--accent-color);"></i>
                <h4 class="fs-6 fw-bold">{{ $content['highlight_'.$i.'_title'] }}</h4>
                <p class="small text-muted mb-0">{{ $content['highlight_'.$i.'_body'] ?? '' }}</p>
              </div>
            </div>
          @endif
        @endfor
      </div>
    @endif

    @if($page === 'contact')
      @if(!empty($content['body']))
        <p class="text-center text-muted mb-4">{{ $content['body'] }}</p>
      @endif
      <div class="contact-clean-grid">
        @if(filled($business->address))
          <div class="contact-clean-item">
            <div class="contact-clean-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="contact-clean-body">
              <h5>Location</h5>
              <p class="mb-0">{{ $business->address }}</p>
            </div>
          </div>
          <div class="contact-clean-divider"></div>
        @endif
        @if(filled($business->phone))
          <div class="contact-clean-item">
            <div class="contact-clean-icon"><i class="bi bi-telephone-fill"></i></div>
            <div class="contact-clean-body">
              <h5>Phone</h5>
              <a href="tel:{{ $business->phone }}" class="contact-clean-link">{{ $business->phone }}</a>
              @if($whatsappDigits)
                <a href="https://wa.me/{{ $whatsappDigits }}" target="_blank" rel="noopener" class="contact-wa-pill mt-2 d-inline-block">
                  <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
              @endif
            </div>
          </div>
          <div class="contact-clean-divider"></div>
        @endif
        @if(filled($business->email))
          <div class="contact-clean-item">
            <div class="contact-clean-icon"><i class="bi bi-envelope-fill"></i></div>
            <div class="contact-clean-body">
              <h5>Email</h5>
              <a href="mailto:{{ $business->email }}" class="contact-clean-link">{{ $business->email }}</a>
            </div>
          </div>
        @endif
      </div>
      @if(!empty($content['hours']))
        <p class="text-center mt-4 mb-4"><i class="bi bi-clock"></i> {{ $content['hours'] }}</p>
      @endif

      @if(!empty($content['show_contact_form']))
        <div class="row justify-content-center mt-4">
          <div class="col-lg-8">
            <form method="POST" action="{{ route('public.site.contact.submit', $website->slug) }}" class="php-email-form">
              @csrf
              <div class="row gy-3">
                <div class="col-md-6">
                  <input type="text" name="name" class="form-control" placeholder="Your name" required value="{{ old('name') }}">
                </div>
                <div class="col-md-6">
                  <input type="text" name="phone" class="form-control" placeholder="Phone" value="{{ old('phone') }}">
                </div>
                <div class="col-12">
                  <textarea name="message" class="form-control" rows="5" placeholder="Message" required>{{ old('message') }}</textarea>
                </div>
                @if($errors->any())
                  <div class="col-12"><div class="text-danger small">{{ $errors->first() }}</div></div>
                @endif
                <div class="col-12 text-center">
                  <button type="submit">Send message</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      @endif
    @endif

    @if($page === 'order')
      @if(!empty($content['body']))
        <p class="lead text-center">{{ $content['body'] }}</p>
      @endif
      @if(!empty($content['steps']))
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="p-4 rounded" style="background:#f8f9fa;white-space:pre-line;">{{ $content['steps'] }}</div>
          </div>
        </div>
      @endif
      <div class="d-flex flex-wrap justify-content-center gap-3 mt-5">
        @if($whatsappDigits)
          @php $waMsg = urlencode($content['whatsapp_message'] ?? ('Hello '.$business->name.', I would like to order: ')); @endphp
          <a href="https://wa.me/{{ $whatsappDigits }}?text={{ $waMsg }}" target="_blank" rel="noopener" class="btn-getstarted px-4 py-2">
            <i class="bi bi-whatsapp"></i> Order on WhatsApp
          </a>
        @endif
        @if($business->phone)
          <a href="tel:{{ $business->phone }}" class="cta-btn px-4 py-2"><i class="bi bi-telephone"></i> Call to order</a>
        @endif
        <a href="{{ $website->publicUrl('products') }}" class="btn btn-outline-secondary px-4 py-2">View products</a>
      </div>
    @endif

  </div>
</section>
@endsection

@push('styles')
@if($page === 'contact')
<style>
  .contact-clean-grid {
    display: flex; align-items: flex-start; justify-content: center; flex-wrap: wrap;
    gap: 0; border-top: 1px solid rgba(0,0,0,0.08); padding-top: 40px;
  }
  .contact-clean-item { flex: 1 1 220px; max-width: 320px; padding: 0 28px 24px; text-align: center; }
  .contact-clean-divider { width: 1px; min-height: 120px; background: rgba(0,0,0,.08); align-self: stretch; }
  .contact-clean-icon { font-size: 2rem; color: var(--accent-color); margin-bottom: 12px; }
  .contact-clean-body h5 { font-weight: 700; margin-bottom: 8px; }
  .contact-clean-link { color: var(--accent-color); font-weight: 600; text-decoration: none; }
  .contact-wa-pill {
    background: #25d366; color: #fff !important; padding: 6px 14px; border-radius: 20px;
    font-size: .9rem; text-decoration: none;
  }
  @media (max-width: 767px) {
    .contact-clean-divider { display: none; }
    .contact-clean-item { max-width: 100%; border-bottom: 1px solid rgba(0,0,0,.06); padding-bottom: 28px; }
  }
</style>
@endif
@endpush
