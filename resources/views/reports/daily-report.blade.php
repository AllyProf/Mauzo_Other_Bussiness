@extends('reports._layout')

@section('report-content')
@php
  $d = $data;
  $formatMetric = function ($value, $format) {
      return $format === 'money' ? money($value) : number_format((float) $value);
  };
@endphp

<div class="tile mb-0">
  <div class="tile-title-w-btn">
    <div class="title">
      <h3 class="mb-0"><i class="fa fa-calendar mr-2"></i>{{ __('reports.daily.title') }}</h3>
    </div>
    <div class="btn-group btn-group-sm d-print-none">
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="fa fa-print"></i> {{ __('reports.daily.print') }}
      </button>
    </div>
  </div>
  <div class="tile-body">
    <div class="daily-report-meta">
      <div>
        <span class="text-muted small font-weight-bold text-uppercase">{{ __('menu.report_date') }}</span>
        <div class="font-weight-bold">{{ \Carbon\Carbon::parse($d['report_date'])->format('m / d / Y') }}</div>
      </div>
      <div class="text-muted small">
        {{ __('reports.daily.period_note', ['period' => $d['period_label']]) }}
      </div>
    </div>

    <div class="table-responsive">
      <table class="daily-report-table">
        <thead>
          <tr>
            <th style="width:28%;"></th>
            <th colspan="2">{{ $d['report_date_label'] }}</th>
            <th colspan="2">{{ $d['period_label'] }}</th>
          </tr>
        </thead>
        <tbody>
          <tr class="section-head">
            <td>{{ __('reports.daily.section_performance') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.daily') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.period') }}</td>
          </tr>
          @foreach($d['metrics'] as $metric)
          <tr>
            <td class="metric-label">{{ $metric['label'] }}</td>
            <td colspan="2" class="num">{{ $formatMetric($metric['day'], $metric['format']) }}</td>
            <td colspan="2" class="num">{{ $formatMetric($metric['period'], $metric['format']) }}</td>
          </tr>
          @endforeach

          <tr class="section-head">
            <td>{{ __('reports.daily.section_payments') }}</td>
            <td class="text-center">{{ __('reports.daily.orders') }}</td>
            <td class="text-center">{{ __('reports.daily.amount') }}</td>
            <td class="text-center">{{ __('reports.daily.orders') }}</td>
            <td class="text-center">{{ __('reports.daily.amount') }}</td>
          </tr>
          @forelse($d['sources'] as $source)
          <tr>
            <td class="metric-label">{{ $source['label'] }}</td>
            <td class="num">{{ number_format($source['day_orders']) }}</td>
            <td class="num">{{ money($source['day_amount']) }}</td>
            <td class="num">{{ number_format($source['period_orders']) }}</td>
            <td class="num">{{ money($source['period_amount']) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-3">{{ __('reports.daily.no_payments') }}</td>
          </tr>
          @endforelse
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.total') }}</td>
            <td class="num">{{ number_format($d['source_totals']['day_orders']) }}</td>
            <td class="num">{{ money($d['source_totals']['day_amount']) }}</td>
            <td class="num">{{ number_format($d['source_totals']['period_orders']) }}</td>
            <td class="num">{{ money($d['source_totals']['period_amount']) }}</td>
          </tr>

          <tr class="section-head">
            <td>{{ __('reports.daily.section_expenses') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.daily') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.period') }}</td>
          </tr>
          @forelse($d['expenses'] ?? [] as $expense)
          <tr>
            <td class="metric-label">{{ $expense['label'] }}</td>
            <td colspan="2" class="num">{{ money($expense['day']) }}</td>
            <td colspan="2" class="num">{{ money($expense['period']) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-3">{{ __('reports.daily.no_expenses') }}</td>
          </tr>
          @endforelse
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.total_expenses') }}</td>
            <td colspan="2" class="num">{{ money($d['expense_totals']['day'] ?? 0) }}</td>
            <td colspan="2" class="num">{{ money($d['expense_totals']['period'] ?? 0) }}</td>
          </tr>
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.net_cash') }}</td>
            <td colspan="2" class="num">{{ money($d['net_cash']['day'] ?? 0) }}</td>
            <td colspan="2" class="num">{{ money($d['net_cash']['period'] ?? 0) }}</td>
          </tr>
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.net_profit') }}</td>
            <td colspan="2" class="num">{{ money($d['net_profit']['day'] ?? 0) }}</td>
            <td colspan="2" class="num">{{ money($d['net_profit']['period'] ?? 0) }}</td>
          </tr>

          @if(isset($d['circulation']))
          <tr class="section-head">
            <td>{{ __('reports.daily.section_circulation') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.daily') }}</td>
            <td colspan="2" class="text-center">{{ __('reports.daily.period') }}</td>
          </tr>
          <tr>
            <td class="metric-label">{{ __('reports.daily.opening_circulation') }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['day_opening']) }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['period_opening']) }}</td>
          </tr>
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.money_in_circulation') }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['closing']) }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['closing']) }}</td>
          </tr>
          <tr>
            <td class="metric-label">{{ __('reports.daily.available_profit') }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['closing_profit']) }}</td>
            <td colspan="2" class="num">{{ money($d['circulation']['closing_profit']) }}</td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
