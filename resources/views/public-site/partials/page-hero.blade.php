@php
  $heroTitle = $heroTitle ?? 'Welcome';
  $heroSubtitle = $heroSubtitle ?? '';
  $heroImage = $heroImage ?? asset('gp-assets/img/hero-bg.jpg');
@endphp
<section class="site-page-hero dark-background" style="--hero-image: url('{{ $heroImage }}');">
  <div class="site-page-hero__overlay" aria-hidden="true"></div>
  <div class="container position-relative" data-aos="fade-up">
    <div class="row justify-content-center text-center">
      <div class="col-lg-9">
        <h1 class="site-page-hero__title">{{ $heroTitle }}</h1>
        @if(filled($heroSubtitle))
          <p class="site-page-hero__sub mb-0">{{ $heroSubtitle }}</p>
        @endif
      </div>
    </div>
  </div>
</section>
