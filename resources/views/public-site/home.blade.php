@extends('public-site.layout')

@section('title', ($content['hero_title'] ?? $business->name).' — '.$business->name)

@section('content')
@php
  $cta = trim((string) ($content['hero_button_url'] ?? ''));
  if ($cta === '' || $cta === '/order' || $cta === 'order') {
      $cta = $website->publicUrl('order');
  } elseif ($cta === 'products' || $cta === '/products') {
      $cta = $website->publicUrl('products');
  } elseif (! str_starts_with($cta, 'http')) {
      $cta = $website->publicUrl(ltrim($cta, '/'));
  }
  $totalInStock = $catalog->flatten(1)->count();
@endphp

<section id="hero" class="hero section dark-background business-hero-plain">
  <div class="container">
    <div class="row justify-content-center text-center" data-aos="fade-up">
      <div class="col-xl-9 col-lg-10">
        <h2>{{ $content['hero_title'] ?? $business->name }}<span>.</span></h2>
        <p class="text-white-50 lead mt-3 mb-0">{{ $content['hero_subtitle'] ?? $website->tagline ?? '' }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
          @if(!empty($content['hero_button_label']))
            <a href="{{ $cta }}" class="btn-getstarted px-4 py-2">{{ $content['hero_button_label'] }}</a>
          @endif
          <a href="#products-we-offer" class="cta-btn px-4 py-2">View products</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="products-we-offer" class="section offerings-section">
  <div class="container section-title" data-aos="fade-up">
    <h2>{{ $content['intro_title'] ?? 'Products we offer' }}</h2>
    @if(!empty($content['intro_body']))
      <p>{{ $content['intro_body'] }}</p>
    @elseif($totalInStock > 0)
      <p>All items currently in stock with prices — updated live from {{ $business->name }} inventory.</p>
    @else
      <p>Visit us or get in touch to see what is in stock today.</p>
    @endif
  </div>

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    @include('public-site.partials.catalog-offerings', ['catalog' => $catalog])

    @if($totalInStock > 0)
      <div class="text-center mt-5 pt-2">
        <a href="{{ $website->publicUrl('order') }}" class="btn-getstarted px-4 py-2 me-2"><i class="bi bi-cart-check"></i> Order now</a>
        <a href="{{ $website->publicUrl('contact') }}" class="cta-btn px-4 py-2">Contact us</a>
      </div>
    @endif
  </div>
</section>
@endsection

@push('styles')
<style>
  .business-hero-plain {
    background: linear-gradient(145deg, var(--accent-color) 0%, #2b2b2b 55%, #111 100%);
    min-height: 420px;
    display: flex;
    align-items: center;
  }
  .business-hero-plain::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,.08), transparent 45%);
    pointer-events: none;
  }
  .business-hero-plain > .container { position: relative; z-index: 1; }
  .offerings-section { background: linear-gradient(180deg, #f4f6f8 0%, #fff 120px); }
</style>
@endpush
