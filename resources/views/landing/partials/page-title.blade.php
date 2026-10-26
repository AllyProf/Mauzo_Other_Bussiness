@php
  $heroBg = $pageTitleBg ?? asset('gp-assets/img/hero-bg.png');
@endphp
<div class="page-title page-title--hero" data-aos="fade">
  <img class="page-title__bg" src="{{ $heroBg }}" alt="" role="presentation">
  <div class="heading">
    <div class="container">
      <div class="row d-flex justify-content-center text-center">
        <div class="col-lg-8">
          <h1>{{ $pageTitle ?? 'Page' }}</h1>
          @if(!empty($pageSubtitle))
            <p class="mb-0">{{ $pageSubtitle }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>
  <nav class="breadcrumbs">
    <div class="container">
      <ol>
        <li><a href="{{ route('landing.index') }}">{{ __('landing.nav.home') }}</a></li>
        @foreach($breadcrumbs ?? [] as $crumb)
          @if($loop->last)
            <li class="current">{{ $crumb['label'] }}</li>
          @else
            <li><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
          @endif
        @endforeach
      </ol>
    </div>
  </nav>
</div>
