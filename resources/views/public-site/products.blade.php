@extends('public-site.layout')

@section('title', ($content['title'] ?? 'Products').' — '.$business->name)

@section('content')
@php
  $caption = trim((string) ($content['body'] ?? ''));
  if ($caption === '' || str_contains(strtolower($caption), 'updated live from') || str_contains(strtolower($caption), 'browse our current')) {
      $caption = $catalog->isEmpty() ? 'Check back soon.' : 'In stock with prices.';
  }
@endphp

@include('public-site.partials.page-hero', [
  'heroTitle' => $content['title'] ?? 'Products',
  'heroSubtitle' => $caption,
  'heroImage' => asset('gp-assets/img/about-pos.png'),
])

<section class="section menu-section">
  <div class="container" data-aos="fade-up">
    @include('public-site.partials.catalog-offerings', ['catalog' => $catalog, 'business' => $business])

    @if(!$catalog->isEmpty())
      <div class="text-center mt-5 pt-3">
        <a href="{{ $website->publicUrl('order') }}" class="btn-getstarted px-4 py-2"><i class="bi bi-cart-check"></i> Place an order</a>
      </div>
    @endif
  </div>
</section>
@endsection
