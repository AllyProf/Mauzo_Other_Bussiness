@php
  $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
  $currentLocale = $currentLocale ?? app()->getLocale();
@endphp
<div class="login-language-switcher">
  @foreach($supportedLocales as $code => $label)
    <form action="{{ route('locale.switch', $code) }}" method="POST" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm {{ $currentLocale === $code ? 'btn-light font-weight-bold' : 'btn-outline-light' }}">
        <span class="mr-1">@include('partials.locale-flag', ['code' => $code, 'size' => 18])</span>{{ $label }}
      </button>
    </form>
  @endforeach
</div>
@once
<style>
  .locale-flag { display: inline-block; vertical-align: middle; border-radius: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,.15); }
</style>
@endonce
