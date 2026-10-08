@extends('reports._layout')

@section('report-content')
@php
  $totalAmount = collect($sources)->sum('day_amount');
  $totalCount = collect($sources)->sum('count');
@endphp

<div class="tile mb-0">
  <div class="tile-title-w-btn">
    <div class="title">
      <h3 class="mb-0"><i class="fa fa-credit-card mr-2"></i>{{ $title ?? 'Payment channels' }}</h3>
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
        <div class="font-weight-bold">{{ \Carbon\Carbon::parse($reportDate)->format('m / d / Y') }}</div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="daily-report-table">
        <thead>
          <tr>
            <th style="width:40%; text-align:left;">{{ __('reports.daily.section_payments') }}</th>
            <th class="text-center">{{ __('reports.daily.orders') }}</th>
            <th class="text-center">{{ __('reports.daily.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($sources as $source)
          <tr>
            <td class="metric-label">{{ $source['label'] }}</td>
            <td class="num">{{ number_format($source['count']) }}</td>
            <td class="num">{{ money($source['day_amount']) }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center text-muted py-3">{{ __('reports.daily.no_payments') }}</td>
          </tr>
          @endforelse
          @if(count($sources))
          <tr class="section-head">
            <td class="metric-label">{{ __('reports.daily.total') }}</td>
            <td class="num">{{ number_format($totalCount) }}</td>
            <td class="num">{{ money($totalAmount) }}</td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
