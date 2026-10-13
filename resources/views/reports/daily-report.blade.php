@extends('reports._layout')

@section('report-content')
@php
  $d = $data;
  $formatMetric = function ($value, $format) {
      return $format === 'money' ? money($value) : number_format((float) $value);
  };
  $summaryMetrics = collect($d['metrics'] ?? [])->whereIn('key', ['orders', 'gross_sales', 'collected', 'outstanding'])->values();
  $paymentRows = collect($d['sources'] ?? [])->filter(function ($s) {
      $hasMethodAmount = ($s['day_amount'] ?? 0) > 0 || ($s['period_amount'] ?? 0) > 0;
      $hasPlatformAmount = collect($s['platforms'] ?? [])->contains(
          fn ($p) => ($p['day_amount'] ?? 0) > 0 || ($p['period_amount'] ?? 0) > 0
      );

      return $hasMethodAmount || $hasPlatformAmount;
  })->values();
  $expenseRows = collect($d['expenses'] ?? [])->filter(fn ($e) => ($e['day'] ?? 0) > 0 || ($e['period'] ?? 0) > 0)->values();
  $reportDay = \Carbon\Carbon::parse($d['report_date'])->format('d M Y');
@endphp

<div class="tile mb-0 daily-report-tile">
  <div class="tile-title-w-btn">
    <div class="title">
      <h3 class="mb-0"><i class="fa fa-calendar mr-2"></i>{{ __('reports.daily.title') }}</h3>
      <p class="text-muted small mb-0 mt-1">{{ $reportDay }} · {{ __('reports.daily.period_note', ['period' => $d['period_label']]) }}</p>
    </div>
    <div class="btn-group btn-group-sm d-print-none">
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="fa fa-print"></i> {{ __('reports.daily.print') }}
      </button>
    </div>
  </div>
  <div class="tile-body pt-2">
    <div class="table-responsive">
      <table class="daily-report-table daily-report-table-compact">
        <thead>
          <tr>
            <th class="metric-col"></th>
            <th class="num-col">{{ $d['report_date_label'] }}</th>
            <th class="num-col">{{ $d['period_label'] }}</th>
          </tr>
        </thead>
        <tbody>
          <tr class="section-head">
            <td colspan="3">{{ __('reports.daily.section_sales') }}</td>
          </tr>
          @foreach($summaryMetrics as $metric)
          <tr>
            <td class="metric-label">{{ $metric['label'] }}</td>
            <td class="num">{{ $formatMetric($metric['day'], $metric['format']) }}</td>
            <td class="num">{{ $formatMetric($metric['period'], $metric['format']) }}</td>
          </tr>
          @endforeach

          <tr class="section-head">
            <td colspan="3">{{ __('reports.daily.section_payments') }}</td>
          </tr>
          @forelse($paymentRows as $source)
          @php
            $platformRows = collect($source['platforms'] ?? [])->filter(
                fn ($p) => ($p['day_amount'] ?? 0) > 0 || ($p['period_amount'] ?? 0) > 0
            );
          @endphp
          @if($platformRows->isNotEmpty())
            @foreach($platformRows as $platform)
            <tr class="payment-platform-row">
              <td class="metric-label">
                <span class="payment-method-hint">{{ $source['label'] }}</span>
                {{ $platform['label'] }}
              </td>
              <td class="num">{{ money($platform['day_amount']) }}</td>
              <td class="num">{{ money($platform['period_amount']) }}</td>
            </tr>
            @endforeach
          @else
          <tr>
            <td class="metric-label">{{ $source['label'] }}</td>
            <td class="num">{{ money($source['day_amount']) }}</td>
            <td class="num">{{ money($source['period_amount']) }}</td>
          </tr>
          @endif
          @empty
          <tr>
            <td colspan="3" class="text-center text-muted py-2">{{ __('reports.daily.no_payments') }}</td>
          </tr>
          @endforelse
          @if($paymentRows->isNotEmpty())
          <tr class="subtotal-row">
            <td class="metric-label">{{ __('reports.daily.total_collected') }}</td>
            <td class="num">{{ money($d['source_totals']['day_amount'] ?? 0) }}</td>
            <td class="num">{{ money($d['source_totals']['period_amount'] ?? 0) }}</td>
          </tr>
          @endif

          <tr class="section-head">
            <td colspan="3">{{ __('reports.daily.section_expenses') }}</td>
          </tr>
          @if($expenseRows->count() > 1)
            @foreach($expenseRows as $expense)
            <tr>
              <td class="metric-label">{{ $expense['label'] }}</td>
              <td class="num">{{ money($expense['day']) }}</td>
              <td class="num">{{ money($expense['period']) }}</td>
            </tr>
            @endforeach
          @endif
          <tr class="subtotal-row">
            <td class="metric-label">{{ __('reports.daily.total_expenses') }}</td>
            <td class="num">{{ money($d['expense_totals']['day'] ?? 0) }}</td>
            <td class="num">{{ money($d['expense_totals']['period'] ?? 0) }}</td>
          </tr>

          <tr class="summary-row">
            <td class="metric-label">{{ __('reports.daily.net_cash_short') }}</td>
            <td class="num">{{ money($d['net_cash']['day'] ?? 0) }}</td>
            <td class="num">{{ money($d['net_cash']['period'] ?? 0) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('report-scripts')
<style>
  .daily-report-tile .tile-title-w-btn .title h3 { font-size: 1.15rem; }
  .daily-report-table-compact .metric-col { width: 40%; }
  .daily-report-table-compact .num-col { width: 30%; text-align: right; }
  .daily-report-table-compact thead th.num-col { text-align: right; }
  .daily-report-table-compact .subtotal-row td {
    background: #f8f9fa;
    font-weight: 600;
  }
  .daily-report-table-compact .summary-row td {
    background: color-mix(in srgb, #940000 8%, #fff);
    font-weight: 700;
    border-top: 2px solid #940000;
  }
  .daily-report-table-compact .section-head td {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding-top: 0.85rem;
  }
  .daily-report-table-compact .payment-platform-row .payment-method-hint {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #6c757d;
    margin-bottom: 0.1rem;
  }
</style>
@endsection
