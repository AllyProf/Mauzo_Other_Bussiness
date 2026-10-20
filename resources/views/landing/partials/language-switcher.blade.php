@php
  $supportedLocales = config('locale.supported', ['en' => 'English', 'sw' => 'Kiswahili']);
  $currentLocale = app()->getLocale();
@endphp
<div class="landing-lang-switcher" role="navigation" aria-label="{{ __('landing.lang.switcher_label') }}">
  @foreach($supportedLocales as $code => $label)
    <form action="{{ route('locale.switch', $code) }}" method="POST" class="landing-lang-switcher__form">
      @csrf
      <button type="submit"
              class="landing-lang-switcher__btn {{ $currentLocale === $code ? 'is-active' : '' }}"
              title="{{ $label }}"
              aria-current="{{ $currentLocale === $code ? 'true' : 'false' }}">
        @include('partials.locale-flag', ['code' => $code, 'size' => 16])
        <span>{{ strtoupper($code) }}</span>
      </button>
    </form>
  @endforeach
</div>
@once
<style>
  .landing-lang-switcher { display: inline-flex; align-items: center; gap: 4px; padding: 3px; border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); }
  .landing-lang-switcher__form { margin: 0; }
  .landing-lang-switcher__btn {
    display: inline-flex; align-items: center; gap: 5px;
    border: none; background: transparent; color: rgba(255,255,255,.85);
    font-size: 11px; font-weight: 700; letter-spacing: .04em;
    padding: 5px 10px; border-radius: 999px; cursor: pointer; line-height: 1;
    transition: background .15s, color .15s;
  }
  .landing-lang-switcher__btn:hover { background: rgba(255,255,255,.15); color: #fff; }
  .landing-lang-switcher__btn.is-active { background: #fff; color: #940000; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
  .landing-lang-switcher .locale-flag { box-shadow: none; }
</style>
@endonce
