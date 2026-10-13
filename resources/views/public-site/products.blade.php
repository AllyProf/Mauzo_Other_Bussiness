@extends('public-site.layout')

@section('title', ($content['title'] ?? 'Products').' — '.$business->name)

@section('content')
<section class="section offerings-section" style="padding-top: 120px;">
  <div class="container section-title" data-aos="fade-up">
    <h2>{{ $content['title'] ?? 'Products' }}</h2>
    @if(!empty($content['body']))
      <p>{{ $content['body'] }}</p>
    @elseif(!$catalog->isEmpty())
      <p>In-stock items and prices from our store.</p>
    @endif
  </div>

  <div class="container" data-aos="fade-up" data-aos-delay="100">
    @include('public-site.partials.catalog-offerings', ['catalog' => $catalog])

    @if(!$catalog->isEmpty())
      <div class="text-center mt-5 pt-3">
        <a href="{{ $website->publicUrl('order') }}" class="btn-getstarted px-4 py-2"><i class="bi bi-cart-check"></i> Place an order</a>
      </div>
    @endif
  </div>
</section>
@endsection

@push('styles')
<style>
  .offerings-section { background: linear-gradient(180deg, #f4f6f8 0%, #fff 160px); }
</style>
@endpush
