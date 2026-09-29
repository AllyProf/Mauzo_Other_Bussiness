@php
  $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
  $currentLocale = $currentLocale ?? app()->getLocale();
  $compact = $compact ?? false;
@endphp
<li class="dropdown app-nav__action app-nav__language">
  <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="{{ __('common.language') }}" title="{{ $supportedLocales[$currentLocale] ?? __('common.language') }}">
    @include('partials.locale-flag', ['code' => $currentLocale, 'size' => 22])
  </a>
  <ul class="app-notification dropdown-menu dropdown-menu-right language-menu">
    <li class="app-notification__title">{{ __('common.language') }}</li>
    <div class="app-notification__content">
      @foreach($supportedLocales as $code => $label)
        <li>
          <form action="{{ route('locale.switch', $code) }}" method="POST">
            @csrf
            <button type="submit" class="app-notification__item {{ $currentLocale === $code ? 'is-current' : '' }}">
              <span class="app-notification__icon">@include('partials.locale-flag', ['code' => $code, 'size' => 30])</span>
              <div>
                <p class="app-notification__message">{{ $label }}</p>
                <p class="app-notification__meta">{{ strtoupper($code) }}</p>
              </div>
              @if($currentLocale === $code)
                <i class="fa fa-check-circle language-menu__check"></i>
              @endif
            </button>
          </form>
        </li>
      @endforeach
    </div>
  </ul>
</li>
@once
<style>
  .locale-flag { display: inline-block; vertical-align: middle; border-radius: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,.15); }
  .language-menu form { margin: 0; }
  .language-menu .app-notification__item { width: 100%; border: 0; background: transparent; text-align: left; align-items: center; color: #333; cursor: pointer; }
  .language-menu .app-notification__item:focus { outline: none; }
  .language-menu .app-notification__item.is-current { background-color: #f5f5f5; }
  .language-menu .app-notification__item.is-current .app-notification__message { font-weight: 700; }
  .language-menu .app-notification__icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; }
  .language-menu .app-notification__message { margin-bottom: 2px; }
  .language-menu__check { margin-left: auto; padding-left: 10px; color: #940000; font-size: 18px; }
</style>
@endonce
