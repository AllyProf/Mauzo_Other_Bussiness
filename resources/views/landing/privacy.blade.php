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
