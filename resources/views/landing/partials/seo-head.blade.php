@php
  $seo = $landingSeo ?? [];
  $platform = $platformName ?? config('app.name');
  $pageTitle = $seo['title'] ?? ($platform.' — '.__('landing.seo.title'));
  $description = $seo['description'] ?? __('landing.seo.description');
  $keywords = $seo['keywords'] ?? __('landing.seo.keywords');
  $canonical = $seo['canonical'] ?? route('landing.index');
  $robots = $seo['robots'] ?? 'index,follow';
  $ogLocale = $seo['og_locale'] ?? (app()->getLocale() === 'sw' ? 'sw_TZ' : 'en_US');
  $ogImage = $seo['og_image'] ?? asset('mauzo_link.png');
  $twitterSite = trim((string) config('seo.twitter_site', ''));
@endphp
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="robots" content="{{ $robots }}">
<meta name="author" content="{{ $platform }}">
<meta name="theme-color" content="#940000">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $platform }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta property="og:locale:alternate" content="{{ $ogLocale === 'sw_TZ' ? 'en_US' : 'sw_TZ' }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">
@if($twitterSite !== '')
<meta name="twitter:site" content="{{ $twitterSite }}">
@endif

@if(!empty($seo['json_ld']))
  @foreach($seo['json_ld'] as $block)
<script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
  @endforeach
@endif
