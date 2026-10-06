@php
    $phoneName = $name ?? 'phone';
    $combined = (bool) ($combined ?? false);
    $nameless = (bool) ($nameless ?? false);
    $countryName = $countryName ?? \App\Support\PhoneCountries::countryFieldFor($phoneName);
    $fieldId = $id ?? ('phone_'.\Illuminate\Support\Str::random(6));
    $parts = \App\Support\PhoneCountries::split($value ?? null);
    $hasError = isset($errors) && $errors->has($phoneName);
    $selectedIso = strtolower((string) ($countryValue ?? $parts['iso']));
    if (! \App\Support\PhoneCountries::isValidIso($selectedIso)) {
        $selectedIso = \App\Support\PhoneCountries::DEFAULT_ISO;
    }
    $localValue = $localValue ?? $parts['local'];
    $countries = \App\Support\PhoneCountries::all();
    $selected = collect($countries)->firstWhere('iso', $selectedIso);
@endphp
<div class="input-group phone-country-input {{ ($size ?? null) === 'sm' ? 'input-group-sm' : '' }}" data-phone-country>
  <div class="input-group-prepend">
    <button type="button" class="btn btn-outline-secondary phone-country-toggle" aria-haspopup="listbox" title="{{ $selected['name'] }}">
      <img class="phone-country-flag" src="https://flagcdn.com/w20/{{ $selected['iso'] }}.png" alt="" width="20" height="15" loading="lazy">
      <span class="phone-country-dial">+{{ $selected['dial'] }}</span>
      <i class="fa fa-caret-down ml-1"></i>
    </button>
    <div class="phone-country-menu" role="listbox">
      <input type="text" class="form-control form-control-sm phone-country-search" placeholder="Search country or code" data-typewriter="off" autocomplete="off">
      <ul class="phone-country-list">
        @foreach($countries as $country)
        <li data-iso="{{ $country['iso'] }}" data-dial="{{ $country['dial'] }}" data-search="{{ strtolower($country['name']) }} +{{ $country['dial'] }} {{ $country['iso'] }}" class="{{ $country['iso'] === $selectedIso ? 'active' : '' }}">
          <img src="https://flagcdn.com/w20/{{ $country['iso'] }}.png" alt="" width="20" height="15" loading="lazy">
          <span class="phone-country-name">{{ $country['name'] }}</span>
          <span class="phone-country-code">+{{ $country['dial'] }}</span>
        </li>
        @endforeach
      </ul>
    </div>
  </div>
  <input type="hidden" @unless($combined || $nameless) name="{{ $countryName }}" @endunless value="{{ $selectedIso }}" class="phone-country-value">
  @if($combined && ! $nameless)
  <input type="hidden" name="{{ $phoneName }}" class="phone-country-combined"
         value="{{ filled($localValue) ? '+'.$selected['dial'].ltrim(preg_replace('/\D+/', '', (string) $localValue), '0') : '' }}">
  @endif
  <input class="form-control phone-country-local {{ $inputClass ?? '' }} {{ $hasError ? 'is-invalid' : '' }}" type="tel" id="{{ $fieldId }}"
         @unless($combined || $nameless) name="{{ $phoneName }}" @endunless value="{{ $localValue }}"
         placeholder="{{ $placeholder ?? '712 345 678' }}" maxlength="15" inputmode="numeric" autocomplete="tel-national" @if($required ?? false) required @endif>
</div>
@if($hasError)
  <small class="text-danger d-block">{{ $errors->first($phoneName) }}</small>
@endif

@once
<style>
  .phone-country-input { position: relative; flex-wrap: nowrap; }
  .phone-country-toggle { display: inline-flex; align-items: center; gap: 6px; background: #f8f9fa; border-color: #ced4da; color: #495057; }
  .phone-country-toggle:hover, .phone-country-toggle:focus { background: #eef0f2; color: #495057; }
  .phone-country-flag { border-radius: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,.08); }
  .phone-country-menu {
    display: none; position: absolute; top: 100%; left: 0; z-index: 1060; width: 290px; max-width: calc(100vw - 24px); margin-top: 4px;
    background: #fff; border: 1px solid #dee2e6; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 8px;
  }
  .phone-country-input.open .phone-country-menu { display: block; }
  .phone-country-list { list-style: none; margin: 6px 0 0; padding: 0; max-height: 240px; overflow-y: auto; }
  .phone-country-list li { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; }
  .phone-country-list li:hover { background: #f4f4f4; }
  .phone-country-list li.active { background: rgba(148,0,0,.08); font-weight: 600; }
  .phone-country-list li img { border-radius: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,.08); flex: 0 0 20px; }
  .phone-country-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .phone-country-code { color: #6c757d; }
</style>
<script>
(function () {
  if (window.__phoneCountryBound) return;
  window.__phoneCountryBound = true;

  function close(wrap) { wrap.classList.remove('open'); }

  function syncCombined(wrap) {
    var hidden = wrap.querySelector('.phone-country-combined');
    if (!hidden) return;
    var local = wrap.querySelector('.phone-country-local').value.replace(/\D+/g, '').replace(/^0+/, '');
    var dial = (wrap.querySelector('.phone-country-dial').textContent || '').replace(/\D+/g, '');
    hidden.value = local ? '+' + dial + local : '';
  }

  function selectCountry(wrap, li) {
    wrap.querySelectorAll('.phone-country-list li.active').forEach(function (x) { x.classList.remove('active'); });
    li.classList.add('active');
    wrap.querySelector('.phone-country-value').value = li.dataset.iso;
    wrap.querySelector('.phone-country-flag').src = 'https://flagcdn.com/w20/' + li.dataset.iso + '.png';
    wrap.querySelector('.phone-country-dial').textContent = '+' + li.dataset.dial;
    wrap.querySelector('.phone-country-toggle').title = li.querySelector('.phone-country-name').textContent;
    syncCombined(wrap);
  }

  function resolveWrap(target) {
    if (!target) return null;
    if (typeof target === 'string') target = document.querySelector(target);
    if (target && target.jquery) target = target[0];
    if (!target) return null;
    return target.matches('[data-phone-country]') ? target : target.closest('[data-phone-country]');
  }

  window.phoneCountryInput = {
    set: function (target, fullNumber) {
      var wrap = resolveWrap(target);
      if (!wrap) return;
      var raw = String(fullNumber || '').trim();
      var digits = raw.replace(/\D+/g, '');
      var items = wrap.querySelectorAll('.phone-country-list li');
      var match = null;
      if (digits && (raw.charAt(0) === '+' || digits.length > 10)) {
        items.forEach(function (li) {
          if (digits.indexOf(li.dataset.dial) === 0 && (!match || li.dataset.dial.length > match.dataset.dial.length)) match = li;
        });
        if (digits.indexOf('255') === 0) match = wrap.querySelector('.phone-country-list li[data-iso="tz"]');
      }
      if (!match) match = wrap.querySelector('.phone-country-list li[data-iso="tz"]');
      selectCountry(wrap, match);
      wrap.querySelector('.phone-country-local').value = digits && (raw.charAt(0) === '+' || digits.length > 10)
        ? digits.slice(match.dataset.dial.length)
        : digits.replace(/^0+/, '');
      syncCombined(wrap);
    },
    get: function (target) {
      var wrap = resolveWrap(target);
      if (!wrap) return '';
      syncCombined(wrap);
      var local = wrap.querySelector('.phone-country-local').value.replace(/\D+/g, '').replace(/^0+/, '');
      var dial = (wrap.querySelector('.phone-country-dial').textContent || '').replace(/\D+/g, '');
      return local ? '+' + dial + local : '';
    }
  };

  document.addEventListener('input', function (e) {
    if (e.target.classList && e.target.classList.contains('phone-country-local')) {
      syncCombined(e.target.closest('[data-phone-country]'));
    }
  });

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.phone-country-toggle');
    var item = e.target.closest('.phone-country-list li');
    var openWraps = document.querySelectorAll('.phone-country-input.open');

    if (toggle) {
      var wrap = toggle.closest('[data-phone-country]');
      var localInput = wrap.querySelector('.phone-country-local');
      if (localInput && (localInput.readOnly || localInput.disabled)) return;
      var wasOpen = wrap.classList.contains('open');
      openWraps.forEach(close);
      if (!wasOpen) {
        wrap.classList.add('open');
        var search = wrap.querySelector('.phone-country-search');
        search.value = '';
        wrap.querySelectorAll('.phone-country-list li').forEach(function (li) { li.style.display = ''; });
        search.focus();
        var active = wrap.querySelector('.phone-country-list li.active');
        if (active) active.scrollIntoView({ block: 'nearest' });
      }
      return;
    }

    if (item) {
      var w = item.closest('[data-phone-country]');
      selectCountry(w, item);
      close(w);
      var input = w.querySelector('input[type="tel"]');
      if (input) {
        input.focus();
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      return;
    }

    if (!e.target.closest('.phone-country-menu')) openWraps.forEach(close);
  });

  document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('phone-country-search')) return;
    var q = e.target.value.trim().toLowerCase().replace(/^\+/, '');
    e.target.closest('[data-phone-country]').querySelectorAll('.phone-country-list li').forEach(function (li) {
      li.style.display = !q || li.dataset.search.indexOf(q) > -1 ? '' : 'none';
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.target.classList.contains('phone-country-search')) {
      var wrap = e.target.closest('[data-phone-country]');
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(wrap); }
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = Array.prototype.find.call(wrap.querySelectorAll('.phone-country-list li'), function (li) { return li.style.display !== 'none'; });
        if (first) first.click();
      }
    }
  });
})();
</script>
@endonce
