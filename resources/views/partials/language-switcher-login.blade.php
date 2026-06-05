@php
  $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
  $currentLocale = $currentLocale ?? app()->getLocale();
@endphp
<div class="login-language-switcher">
  @foreach($supportedLocales as $code => $label)
    <form action="{{ route('locale.switch', $code) }}" method="POST" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm {{ $currentLocale === $code ? 'btn-light font-weight-bold' : 'btn-outline-light' }}">
        {{ $label }}
      </button>
    </form>
  @endforeach
</div>
