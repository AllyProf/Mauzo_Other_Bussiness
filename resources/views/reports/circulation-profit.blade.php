@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $filtered = !empty($data['business_type_filtered']);
  $tableRows = $data['tableRows'];
  $profitTrendLegend = $filtered
    ? [['color' => '#28a745', 'type' => 'line', 'label' => 'Gross Profit']]
    : [
      ['color' => '#28a745', 'type' => 'line', 'label' => 'Gross Profit'],
      ['color' => '#940000', 'type' => 'line', 'label' => 'Net Profit'],
    ];
@endphp

@include('reports.partials.stat-widgets', ['widgets' => array_values(array_filter([
  !$filtered ? ['icon' => 'fa-money', 'color' => 'primary', 'label' => 'Current Circulation', 'value' => money($s['current_circulation'])] : null,
  ['icon' => 'fa-line-chart', 'color' => 'success', 'label' => $filtered ? 'Period Gross Profit' : 'Current Profit Balance', 'value' => money($s['current_profit'])],
  !$filtered ? ['icon' => 'fa-arrow-up', 'color' => 'info', 'label' => 'Peak Circulation', 'value' => money($s['peak_circulation'])] : null,
  ['icon' => 'fa-calculator', 'color' => 'warning', 'label' => 'Avg Daily Gross Profit', 'value' => money($s['avg_daily_gross_profit'] ?? 0)],
]))])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Daily Profit Trend',
    'id' => 'profitTrendChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $profitTrendLegend,
  ])
  @include('reports.partials.chart-tile', [
    'title' => $filtered ? 'Daily Gross Profit' : 'Circulation Balance',
    'id' => 'balanceChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $filtered
      ? [['color' => '#940000', 'type' => 'bar', 'label' => 'Gross Profit']]
      : [['color' => '#940000', 'type' => 'line', 'label' => 'Circulation Balance']],
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Daily Breakdown</h3>
      <div class="tile-body">
        <p class="text-muted small mb-3">
          <i class="fa fa-info-circle"></i>
          Charts show <strong>daily</strong> profit and circulation balance. The table below shows end-of-day balances.
        </p>
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                @if(!$filtered)<th class="money-col">Closing Circulation</th>@endif
                <th class="money-col">Gross Profit</th>
                <th class="money-col">Closing Profit</th>
                @if(!$filtered)<th class="money-col">Net Profit</th>@endif
                <th>{{ __('tables.columns.status') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tableRows as $row)
              <tr>
                <td><strong>{{ $row['date_label'] }}</strong></td>
                @if(!$filtered)<td class="money-col">{{ money($row['closing_circulation']) }}</td>@endif
                <td class="money-col text-success">{{ money($row['gross_profit'] ?? 0) }}</td>
                <td class="money-col font-weight-bold">{{ money($row['closing_profit']) }}</td>
                @if(!$filtered)
                <td class="money-col {{ ($row['net_profit'] ?? 0) < 0 ? 'text-danger' : '' }} font-weight-bold">
                  {{ money($row['net_profit']) }}
                </td>
                @endif
                <td>
                  <span class="badge badge-{{ $row['status'] === 'finalized' ? 'success' : 'secondary' }}">
                    {{ ucfirst($row['status']) }}
                  </span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="{{ $filtered ? 4 : 6 }}" class="text-center text-muted py-4">No data for this period.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($tableRows->hasPages())
        <div class="report-table-footer">
          <p class="text-muted small mb-0">
            Showing {{ $tableRows->firstItem() }}&ndash;{{ $tableRows->lastItem() }} of {{ $tableRows->total() }} days
          </p>
          {{ $tableRows->links('pagination::bootstrap-4') }}
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('report-scripts')
<script>
(function () {
  var baseScaleOptions = {
    scaleShowGridLines: true,
    scaleGridLineColor: 'rgba(0,0,0,.06)',
    scaleLineColor: 'rgba(0,0,0,.12)',
    scaleFontColor: '#666',
    scaleFontSize: 11,
    responsive: true,
    maintainAspectRatio: false,
  };

  var profitDatasets = [
    {
      label: 'Gross Profit',
      fillColor: 'rgba(40,167,69,0.05)',
      strokeColor: '#28a745',
      pointColor: '#28a745',
      pointStrokeColor: '#fff',
      data: @json($data['gross_profit'] ?? $data['profit'])
    },
    @if(!$filtered)
    {
      label: 'Net Profit',
      fillColor: 'rgba(148,0,0,0.05)',
      strokeColor: '#940000',
      pointColor: '#940000',
      pointStrokeColor: '#fff',
      data: @json($data['net_profit'])
    },
    @endif
  ];

  new Chart(document.getElementById('profitTrendChart').getContext('2d')).Line({
    labels: @json($data['labels']),
    datasets: profitDatasets
  }, Object.assign({}, baseScaleOptions, {
    scaleBeginAtZero: false,
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    multiTooltipTemplate: '<%= datasetLabel %>: TZS <%= value %>',
  }));

  @if($filtered)
  new Chart(document.getElementById('balanceChart').getContext('2d')).Bar({
    labels: @json($data['labels']),
    datasets: [{
      label: 'Gross Profit',
      fillColor: '#940000',
      strokeColor: '#940000',
      highlightFill: '#b30000',
      highlightStroke: '#940000',
      data: @json($data['gross_profit'] ?? $data['profit']),
    }],
  }, Object.assign({}, baseScaleOptions, {
    scaleBeginAtZero: true,
    barShowStroke: false,
    tooltipTemplate: '<%if (label){%><%=label%>: <%}%>TZS <%= value %>',
  }));
  @else
  new Chart(document.getElementById('balanceChart').getContext('2d')).Line({
    labels: @json($data['labels']),
    datasets: [{
      label: 'Circulation Balance',
      fillColor: 'rgba(148,0,0,0.05)',
      strokeColor: '#940000',
      pointColor: '#940000',
      pointStrokeColor: '#fff',
      data: @json($data['circulation']),
    }],
  }, Object.assign({}, baseScaleOptions, {
    scaleBeginAtZero: true,
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    tooltipTemplate: '<%if (label){%><%=label%>: <%}%>TZS <%= value %>',
  }));
  @endif
})();
</script>
@endsection
