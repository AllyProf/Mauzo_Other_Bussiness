@php
  $reportMenuOptions = $reportMenuOptions ?? business_report_menu_options();
  $activeReportUrl = collect($reportMenuOptions)->firstWhere('active')['url']
    ?? ($reportMenuOptions[0]['url'] ?? url()->current());
  $isSingleDayReport = request()->routeIs('reports.daily-report', 'reports.payment-channels');
  $hubFrom = $dateRange['from'] ?? request('start_date', now()->toDateString());
  $hubTo = $dateRange['to'] ?? request('end_date', now()->toDateString());
  if ($isSingleDayReport) {
    $hubTo = $reportDate ?? request('report_date', $hubTo);
    $hubFrom = $hubTo;
  }
@endphp
@if(count($reportMenuOptions) > 0)
<style>
  .reports-hub-filter {
    background: #fff;
    padding: 0 0 1rem;
    border-bottom: 2px solid #940000;
    margin-bottom: 1.25rem;
  }
  .reports-hub-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: #343a40;
    margin-bottom: 0.35rem;
  }
  .reports-hub-form .form-control {
    border: 1px solid #ced4da;
    border-radius: 3px;
    height: calc(1.5em + 0.75rem + 2px);
  }
  .btn-reports-submit {
    background: #940000;
    border-color: #940000;
    color: #fff;
    font-weight: 700;
    border-radius: 3px;
    padding: 0.45rem 1.25rem;
    margin-top: 1.45rem;
  }
  .btn-reports-submit:hover,
  .btn-reports-submit:focus {
    background: #7a0000;
    border-color: #7a0000;
    color: #fff;
  }
  @media (max-width: 767.98px) {
    .btn-reports-submit { margin-top: 0; }
    .reports-hub-actions { margin-top: 0; }
  }
  .reports-hub-submit-col .reports-hub-label-spacer {
    visibility: hidden;
    display: block;
    height: 0;
    margin-bottom: 0.35rem;
  }
  @media (min-width: 768px) {
    .reports-hub-submit-col .reports-hub-label-spacer {
      height: auto;
      min-height: 1.2em;
    }
  }
  .reports-hub-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 1.45rem;
  }
  .reports-hub-actions .btn-reports-submit {
    margin-top: 0;
    white-space: nowrap;
  }
  .reports-hub-actions .report-export-link {
    white-space: nowrap;
  }
</style>
<div class="reports-hub-filter d-print-none">
  <form method="GET" action="{{ $activeReportUrl }}" id="reportHubForm" class="reports-hub-form">
    @if(request('business_type'))
      <input type="hidden" name="business_type" value="{{ request('business_type') }}">
    @endif
    <div class="row align-items-end">
      <div class="col-12 col-md-4 col-lg-3 form-group">
        <label class="reports-hub-label" for="report-type-selector">{{ __('menu.select_report') }}</label>
        <select id="report-type-selector" name="report_key" class="form-control">
          @foreach($reportMenuOptions as $option)
            <option value="{{ $option['url'] }}" data-key="{{ $option['key'] }}" {{ !empty($option['active']) ? 'selected' : '' }}>
              {{ $option['label'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-6 col-md-3 col-lg-2 form-group" id="hub-from-wrap">
        <label class="reports-hub-label" for="hub_start_date">{{ __('menu.from_date') }}</label>
        <input type="date" name="start_date" id="hub_start_date" class="form-control" value="{{ $hubFrom }}">
      </div>
      <div class="col-6 col-md-3 col-lg-2 form-group">
        <label class="reports-hub-label" for="hub_end_date">{{ __('menu.to_date') }}</label>
        <input type="date" name="end_date" id="hub_end_date" class="form-control" value="{{ $hubTo }}">
        <input type="hidden" name="report_date" id="hub_report_date" value="{{ $hubTo }}" disabled>
      </div>
      <div class="col-12 col-md-auto col-lg-auto form-group reports-hub-submit-col">
        <span class="reports-hub-label reports-hub-label-spacer" aria-hidden="true">&nbsp;</span>
        <div class="reports-hub-actions">
          <button type="submit" class="btn btn-reports-submit">{{ __('menu.submit_report') }}</button>
          @include('reports.partials.export-buttons')
        </div>
      </div>
    </div>
  </form>
</div>
<script>
(function () {
  var form = document.getElementById('reportHubForm');
  var selector = document.getElementById('report-type-selector');
  var fromWrap = document.getElementById('hub-from-wrap');
  var startInput = document.getElementById('hub_start_date');
  var endInput = document.getElementById('hub_end_date');
  var reportDateInput = document.getElementById('hub_report_date');
  if (!form || !selector || form.dataset.bound === '1') return;
  form.dataset.bound = '1';

  function syncDailyMode() {
    var opt = selector.options[selector.selectedIndex];
    var key = opt && opt.getAttribute('data-key');
    var isDaily = key === 'daily-report' || key === 'payment-channels';
    if (fromWrap) fromWrap.style.display = isDaily ? 'none' : '';
    if (startInput) startInput.disabled = !!isDaily;
    if (endInput) {
      endInput.name = isDaily ? 'report_date' : 'end_date';
      var label = endInput.closest('.form-group') && endInput.closest('.form-group').querySelector('label');
      if (label) {
        label.textContent = isDaily
          ? @json(__('menu.report_date'))
          : @json(__('menu.to_date'));
      }
    }
    if (reportDateInput) reportDateInput.disabled = true;
  }

  selector.addEventListener('change', syncDailyMode);
  form.addEventListener('submit', function () {
    form.setAttribute('action', selector.value);
    syncDailyMode();
  });
  syncDailyMode();

  function buildExportUrl(format) {
    var opt = selector.options[selector.selectedIndex];
    var key = opt && opt.getAttribute('data-key');
    if (!key) return '#';
    var base = @json(url('/reports/export'));
    var params = new URLSearchParams();
    params.set('format', format);
    if (form.querySelector('[name=business_type]')) {
      params.set('business_type', form.querySelector('[name=business_type]').value);
    }
    var isDaily = key === 'daily-report' || key === 'payment-channels';
    if (isDaily && endInput && endInput.value) {
      params.set('report_date', endInput.value);
    } else {
      if (startInput && startInput.value && !startInput.disabled) {
        params.set('start_date', startInput.value);
      }
      if (endInput && endInput.value) {
        params.set('end_date', endInput.value);
      }
    }
    @if(request()->boolean('cashiers_only'))
    params.set('cashiers_only', '1');
    @endif
    return base + '/' + key + '?' + params.toString();
  }

  document.querySelectorAll('.report-export-link').forEach(function (link) {
    link.addEventListener('click', function () {
      var format = link.getAttribute('data-format');
      var url = buildExportUrl(format);
      if (url !== '#') {
        link.setAttribute('href', url);
      }
    });
  });
})();
</script>
@endif
