@php
    $emailName = $name ?? 'email';
    $emailId = $id ?? ('email_'.\Illuminate\Support\Str::random(6));
@endphp
<input class="form-control" type="email" id="{{ $emailId }}" name="{{ $emailName }}" value="{{ $value ?? '' }}"
       placeholder="{{ $placeholder ?? 'e.g. info@supplier.com' }}" list="{{ $emailId }}_suggestions" autocomplete="off" data-email-suggest
       @if($required ?? false) required @endif>
<datalist id="{{ $emailId }}_suggestions"></datalist>

@once
<script>
(function () {
  if (window.__emailSuggestBound) return;
  window.__emailSuggestBound = true;

  var domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'live.com', 'ymail.com', 'protonmail.com', 'aol.com', 'mail.com', 'zoho.com', 'gmx.com'];

  document.addEventListener('input', function (e) {
    var input = e.target;
    if (!input.matches || !input.matches('input[data-email-suggest]')) return;
    var list = document.getElementById(input.getAttribute('list'));
    if (!list) return;

    var value = input.value.trim();
    var at = value.indexOf('@');
    var local = at === -1 ? value : value.slice(0, at);
    var typedDomain = at === -1 ? '' : value.slice(at + 1).toLowerCase();

    list.innerHTML = '';
    if (!local || /\s/.test(local)) return;

    domains
      .filter(function (d) { return !typedDomain || (d.indexOf(typedDomain) === 0 && d !== typedDomain); })
      .forEach(function (d) {
        var opt = document.createElement('option');
        opt.value = local + '@' + d;
        list.appendChild(opt);
      });
  });
})();
</script>
@endonce
