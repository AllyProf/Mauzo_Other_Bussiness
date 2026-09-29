@once
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
  .flatpickr-calendar { font-family: inherit; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); }
  .flatpickr-day.selected, .flatpickr-day.selected:hover, .flatpickr-day.selected:focus,
  .flatpickr-day.startRange, .flatpickr-day.endRange {
    background: #940000; border-color: #940000; color: #fff;
  }
  .flatpickr-day.today { border-color: #940000; }
  .flatpickr-day.today:hover { background: #940000; border-color: #940000; color: #fff; }
  .flatpickr-months .flatpickr-month, .flatpickr-current-month .flatpickr-monthDropdown-months { color: #212529; }
  .modal .flatpickr-calendar { z-index: 1060; }
  input.js-date-picker[readonly], input.js-date-picker + input[readonly] { background-color: #fff; cursor: pointer; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
  window.appDatePicker = function (el) {
    if (!el || el._flatpickr || typeof flatpickr === 'undefined') {
      return el ? el._flatpickr : null;
    }
    return flatpickr(el, {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd M Y',
      allowInput: false,
      disableMobile: true,
      static: !!el.closest('.modal'),
    });
  };

  window.appSetDate = function (el, isoDate) {
    if (!el) return;
    if (el._flatpickr) {
      el._flatpickr.setDate(isoDate, true);
    } else {
      el.value = isoDate;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }
  };

  window.appAddMonthsIso = function (baseIso, months) {
    var parts = String(baseIso || '').split('-');
    var base = parts.length === 3 ? new Date(+parts[0], +parts[1] - 1, +parts[2]) : new Date();
    var day = base.getDate();
    var target = new Date(base.getFullYear(), base.getMonth() + parseInt(months || 1, 10), 1);
    var lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    target.setDate(Math.min(day, lastDay));
    return target.getFullYear() + '-' + String(target.getMonth() + 1).padStart(2, '0') + '-' + String(target.getDate()).padStart(2, '0');
  };

  window.appTodayIso = function () {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input.js-date-picker').forEach(window.appDatePicker);

    document.addEventListener('click', function (event) {
      var btn = event.target.closest('.js-date-add-months');
      if (!btn) return;
      event.preventDefault();
      var input = document.querySelector(btn.getAttribute('data-target'));
      if (!input) return;
      var today = window.appTodayIso();
      var from = btn.getAttribute('data-from');
      var base = from || (input.value && input.value >= today ? input.value : today);
      window.appSetDate(input, window.appAddMonthsIso(base, btn.getAttribute('data-months')));
    });
  });
</script>
@endpush
@endonce
