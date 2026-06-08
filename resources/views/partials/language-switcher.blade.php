@php
  $supportedLocales = $supportedLocales ?? config('locale.supported', ['en' => 'English']);
  $currentLocale = $currentLocale ?? app()->getLocale();
  $compact = $compact ?? false;
@endphp
<li class="dropdown app-nav__action app-nav__language">
  <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="{{ __('common.language') }}" title="{{ __('common.language') }}">
    <i class="fa fa-globe"></i>
    @unless($compact)
      <span class="d-none d-md-inline ml-2">{{ strtoupper($currentLocale) }}</span>
      <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
    @endunless
  </a>
  <ul class="dropdown-menu dropdown-menu-right">
    <li class="dropdown-header">{{ __('common.language') }}</li>
    @foreach($supportedLocales as $code => $label)
      <li>
        <form action="{{ route('locale.switch', $code) }}" method="POST">
          @csrf
          <button type="submit" class="dropdown-item {{ $currentLocale === $code ? 'active font-weight-bold' : '' }}">
            {{ $label }}
          </button>
        </form>
      </li>
    @endforeach
  </ul>
</li>
