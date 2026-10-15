@extends('reports._layout')

@section('report-content')
@php
  $rows = $dayItemStock ?? [];
  $reportDay = \Carbon\Carbon::parse($reportDate)->format('d M Y');
@endphp

<div class="tile mb-0 daily-items-sold-tile">
  <div class="tile-title-w-btn">
    <div class="title">
      <h3 class="mb-0"><i class="fa fa-cubes mr-2"></i>{{ __('reports.daily_items_sold.title') }}</h3>
      <p class="text-muted small mb-0 mt-1">{{ $reportDay }} · {{ __('reports.daily.items_sold_note') }}</p>
      <p class="small mb-0 mt-1 daily-items-sold-legend">
        <span class="daily-items-sold-legend-sold"><i class="fa fa-circle"></i> {{ __('reports.daily_items_sold.legend_sold') }}</span>
        <span class="daily-items-sold-legend-not-sold ml-3"><i class="fa fa-circle"></i> {{ __('reports.daily_items_sold.legend_not_sold') }}</span>
      </p>
    </div>
  </div>
  <div class="tile-body pt-2">
    <div class="table-responsive">
      <table class="table table-hover table-bordered report-table mb-0 daily-items-sold-table">
        <thead>
          <tr>
            <th>{{ __('tables.columns.item') }}</th>
            <th>{{ __('tables.columns.category') }}</th>
            <th class="text-right">{{ __('reports.daily.col_opening') }}</th>
            <th class="text-right">{{ __('reports.daily.col_sold') }}</th>
            <th class="text-right">{{ __('reports.daily.col_remains') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
          <tr class="{{ !empty($row['was_sold']) ? 'daily-item-row-sold' : 'daily-item-row-not-sold' }}">
            <td><strong>{{ $row['name'] }}</strong></td>
            <td><span class="badge badge-light border">{{ $row['category'] }}</span></td>
            <td class="text-right text-nowrap">{{ $row['opening_display'] }}</td>
            <td class="text-right text-nowrap font-weight-bold daily-item-sold-col">{{ $row['sold_display'] }}</td>
            <td class="text-right text-nowrap">{{ $row['remains_display'] }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">{{ __('reports.daily.no_items_sold') }}</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('report-scripts')
<style>
  .daily-items-sold-tile .tile-title-w-btn .title h3 { font-size: 1.15rem; }
  .daily-items-sold-table thead th { background: #f8f9fa; font-size: 0.85rem; }
  .daily-items-sold-legend-sold { color: #155724; font-weight: 600; }
  .daily-items-sold-legend-sold .fa { color: #28a745; font-size: 0.55rem; vertical-align: middle; }
  .daily-items-sold-legend-not-sold { color: #721c24; font-weight: 600; }
  .daily-items-sold-legend-not-sold .fa { color: #dc3545; font-size: 0.55rem; vertical-align: middle; }
  .daily-items-sold-table tr.daily-item-row-sold { background: color-mix(in srgb, #28a745 14%, #fff); }
  .daily-items-sold-table tr.daily-item-row-sold:hover { background: color-mix(in srgb, #28a745 22%, #fff); }
  .daily-items-sold-table tr.daily-item-row-sold .daily-item-sold-col { color: #155724; }
  .daily-items-sold-table tr.daily-item-row-not-sold { background: color-mix(in srgb, #dc3545 12%, #fff); }
  .daily-items-sold-table tr.daily-item-row-not-sold:hover { background: color-mix(in srgb, #dc3545 20%, #fff); }
  .daily-items-sold-table tr.daily-item-row-not-sold .daily-item-sold-col { color: #721c24; }
</style>
@endsection
