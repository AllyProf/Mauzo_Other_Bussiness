@php
  $faviconPath = is_file(public_path('mauzo_link.png'))
      ? 'mauzo_link.png'
      : (is_file(public_path('gp-assets/img/logo.png')) ? 'gp-assets/img/logo.png' : 'favicon.ico');
  $faviconUrl = asset($faviconPath).(is_file(public_path($faviconPath)) ? '?v='.@filemtime(public_path($faviconPath)) : '');
@endphp
<link rel="icon" type="image/png" href="{{ $faviconUrl }}">
<link rel="shortcut icon" type="image/png" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
