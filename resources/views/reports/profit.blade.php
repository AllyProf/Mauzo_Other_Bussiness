@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $filtered = !empty($data['business_type_filtered']);
  $tableRows = $data['tableRows'];
  $profitLegend = [
    ['color' => '#28a745', 'type' => 'line', 'label' => 'Gross Profit'],
    ['color' => '#6c757d', 'type' => 'line', 'label' => 'COGS'],
  ];
  if (!$filtered) {
    array_splice($profitLegend, 1, 0, [['color' => '#940000', 'type' => 'line', 'label' => 'Net Profit']]);
  }
@endphp

@include('reports.partials.stat-widgets', ['widgets' => array_values(array_filter([
  ['icon' => 'fa-money', 'color' => 'primary', 'label' => 'Gross Sales', 'value' => money($s['gross_sales'])],
  ['icon' => 'fa-line-chart', 'color' => 'success', 'label' => 'Gross Profit', 'value' => money($s['gross_profit'])],
  !$filtered ? ['icon' => 'fa-trophy', 'color' => 'info', 'label' => 'Net Profit', 'value' => money($s['net_profit'])] : null,
  ['icon' => 'fa-percent', 'color' => 'warning', 'label' => 'Avg Margin', 'value' => $s['avg_margin'] . '%'],
]))])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Profit Trend',
    'id' => 'profitChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $profitLegend,
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Sales vs Costs',
    'id' => 'salesCostChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'bar', 'label' => 'Gross Sales'],
      ['color' => '#6c757d', 'type' => 'bar', 'label' => 'COGS'],
    ],
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Daily Profit Breakdown</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                <th class="money-col">Gross Sales</th>
                <th class="money-col">COGS</th>
                <th class="money-col">Gross Profit</th>
                <th class="money-col">Net Profit</th>
                <th class="text-center">Margin</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tableRows as $row)
              <tr>
                <td><strong>{{ $row['date_label'] }}</strong></td>
                <td class="money-col">{{ money($row['gross_sales']) }}</td>
                <td class="money-col text-muted">{{ money($row['cost_of_goods']) }}</td>
                <td class="money-col text-success font-weight-bold">{{ money($row['gross_profit']) }}</td>
                <td class="money-col font-weight-bold">{{ $row['net_profit'] !== null ? money($row['net_profit']) : '—' }}</td>
                <td class="text-center"><span class="badge badge-light border">{{ $row['margin'] }}%</span></td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No profit records found for this period.</td>
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
    scaleBeginAtZero: true,
    responsive: true,
    maintainAspectRatio: false,
  };

  var datasets = [
    {
      label: 'Gross Profit',
      fillColor: 'rgba(40,167,69,0.05)',
      strokeColor: '#28a745',
      pointColor: '#28a745',
      pointStrokeColor: '#fff',
      data: @json($data['gross_profit'])
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
    {
      label: 'COGS',
      fillColor: 'rgba(108,117,125,0.05)',
      strokeColor: '#6c757d',
      pointColor: '#6c757d',
      pointStrokeColor: '#fff',
      data: @json($data['cogs'])
    }
  ];

  new Chart(document.getElementById('profitChart').getContext('2d')).Line({
    labels: @json($data['labels']),
    datasets: datasets
  }, Object.assign({}, baseScaleOptions, {
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    multiTooltipTemplate: '<%= datasetLabel %>: TZS <%= value %>',
  }));

  new Chart(document.getElementById('salesCostChart').getContext('2d')).Bar({
    labels: @json($data['labels']),
    datasets: [
      {
        label: 'Gross Sales',
        fillColor: '#940000',
        strokeColor: '#940000',
        highlightFill: '#b30000',
        highlightStroke: '#940000',
        data: @json($data['gross_sales'])
      },
      {
        label: 'COGS',
        fillColor: '#6c757d',
        strokeColor: '#6c757d',
        highlightFill: '#868e96',
        highlightStroke: '#6c757d',
        data: @json($data['cogs'])
      }
    ]
  }, Object.assign({}, baseScaleOptions, {
    barShowStroke: false,
    multiTooltipTemplate: '<%= datasetLabel %>: TZS <%= value %>',
  }));
})();
</script>
@endsection
