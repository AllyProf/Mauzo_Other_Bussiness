@extends('landing.layout')

@section('body_class', 'privacy-page')
@section('header_class', 'fixed-top')

@section('seo_head')
  @include('landing.partials.seo-head')
@endsection

@section('content')
  @include('landing.partials.page-title', [
    'pageTitle' => __('privacy.page_title'),
    'pageSubtitle' => __('privacy.page_subtitle'),
    'breadcrumbs' => [
      ['label' => __('privacy.breadcrumb')],
    ],
  ])

  @php
    $replace = [
      'platform' => $platformName,
      'company' => $companyName,
      'email' => $supportEmail !== '' ? $supportEmail : '—',
      'phone' => $supportPhone !== '' ? $supportPhone : '—',
      'address' => $publicAddress !== '' ? $publicAddress : '—',
    ];
  @endphp

  <section class="section privacy-policy">
    <div class="container" data-aos="fade-up">
      <div class="privacy-policy__inner">
        <div class="privacy-policy__meta text-center">
          <span>{{ __('privacy.updated_label') }}: <time datetime="{{ $updatedAt->toDateString() }}">{{ $updatedAt->translatedFormat('d M Y') }}</time></span>
        </div>

        <p class="privacy-policy__intro">{{ __('privacy.intro', $replace) }}</p>

        @foreach(__('privacy.sections') as $section)
          <article class="privacy-policy__section">
            <h2>{{ $section['title'] }}</h2>
            @foreach($section['paragraphs'] ?? [] as $paragraph)
              <p>{{ trans($paragraph, $replace) }}</p>
            @endforeach
            @if(!empty($section['bullets']) && is_array($section['bullets']))
              <ul>
                @foreach($section['bullets'] as $bullet)
                  <li>{{ trans($bullet, $replace) }}</li>
                @endforeach
              </ul>
            @endif
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endsection

@push('styles')
<style>
  /* Critical page-title hero styles inlined so layout cannot break if brand CSS is cached stale */
  .page-title.page-title--hero {
    position: relative;
    color: #fff;
    background-color: #111;
    background-position: center;
    background-size: cover;
    background-repeat: no-repeat;
    overflow: hidden;
  }
  .page-title.page-title--hero::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.72) 0%, rgba(0, 0, 0, 0.55) 55%, rgba(0, 0, 0, 0.7) 100%);
  }
  .page-title.page-title--hero .heading,
  .page-title.page-title--hero .breadcrumbs {
    position: relative;
    z-index: 2;
  }
  .page-title.page-title--hero .heading {
    padding: 140px 0 56px;
    border-top: 0;
  }
  .page-title.page-title--hero .heading h1 { color: #fff; }
  .page-title.page-title--hero .heading p {
    color: rgba(255, 255, 255, 0.85);
    margin-top: 12px;
  }
  .page-title.page-title--hero .breadcrumbs {
    background: rgba(0, 0, 0, 0.35);
    padding: 16px 0;
  }
  .page-title.page-title--hero .breadcrumbs ol li,
  .page-title.page-title--hero .breadcrumbs ol li a,
  .page-title.page-title--hero .breadcrumbs ol li.current {
    color: rgba(255, 255, 255, 0.9);
  }
  .page-title.page-title--hero .breadcrumbs ol li+li::before {
    color: rgba(255, 255, 255, 0.55);
  }
  @media (max-width: 768px) {
    .page-title.page-title--hero .heading { padding: 112px 0 40px; }
    .page-title.page-title--hero .heading h1 { font-size: 30px; }
  }

  .privacy-policy {
    padding: 48px 0 88px;
  }
  .privacy-policy__inner {
    max-width: 44rem;
    margin: 0 auto;
    text-align: left;
  }
  .privacy-policy__meta {
    margin-bottom: 1.5rem;
    color: #6c757d;
    font-size: 0.95rem;
  }
  .privacy-policy__intro {
    font-size: 1.05rem;
    line-height: 1.75;
    color: #444;
    margin-bottom: 2rem;
  }
  .privacy-policy__section {
    margin-bottom: 2rem;
  }
  .privacy-policy__section h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    color: #222;
    margin-bottom: 0.75rem;
  }
  .privacy-policy__section p,
  .privacy-policy__section li {
    line-height: 1.75;
    color: #555;
  }
  .privacy-policy__section ul {
    padding-left: 1.25rem;
    margin: 0.5rem 0 0;
  }
  .privacy-policy__section li {
    margin-bottom: 0.5rem;
  }
</style>
@endpush
