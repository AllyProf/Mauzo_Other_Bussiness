@php
  $supportedLocales = config('locale.supported', ['en' => 'English', 'sw' => 'Kiswahili']);
  $currentLocale = app()->getLocale();
@endphp
<div class="site-controls" role="group" aria-label="{{ __('public_site.lang.switcher_label') }}">
  <div class="site-lang-switcher" role="navigation" aria-label="{{ __('public_site.lang.switcher_label') }}">
    @foreach($supportedLocales as $code => $label)
      <form action="{{ route('locale.switch', $code) }}" method="POST" class="site-lang-switcher__form">
        @csrf
        <button type="submit"
                class="site-lang-switcher__btn {{ $currentLocale === $code ? 'is-active' : '' }}"
                title="{{ $label }}"
                aria-current="{{ $currentLocale === $code ? 'true' : 'false' }}">
          @include('partials.locale-flag', ['code' => $code, 'size' => 14])
          <span>{{ strtoupper($code) }}</span>
        </button>
      </form>
    @endforeach
  </div>

  <button type="button"
          id="siteThemeToggle"
          class="site-theme-toggle"
          aria-label="{{ __('public_site.theme.to_light') }}"
          title="{{ __('public_site.theme.to_light') }}">
    <i class="bi bi-moon-stars-fill" data-theme-icon-dark></i>
    <i class="bi bi-sun-fill d-none" data-theme-icon-light></i>
  </button>
</div>
