@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $staffRows = $data['staffRows'];
  $methodColors = ['#940000', '#28a745', '#ffc107', '#17a2b8', '#6c757d', '#343a40'];
  $sourceColors = ['#940000', '#17a2b8', '#28a745', '#ffc107', '#6c757d', '#343a40'];
  $methodLegend = $data['by_method']->values()->map(function ($method, $index) use ($methodColors) {
    return [
      'color' => $methodColors[$index % count($methodColors)],
      'type' => 'bar',
      'label' => $method['label'],
    ];
  })->all();
  $sourceLegend = $data['by_source']->values()->map(function ($source, $index) use ($sourceColors) {
    return [
      'color' => $sourceColors[$index % count($sourceColors)],
      'type' => 'bar',
      'label' => $source['label'],
    ];
  })->all();
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-shopping-bag', 'color' => 'primary', 'label' => 'Total Orders', 'value' => number_format($s['total_orders'])],
  ['icon' => 'fa-money', 'color' => 'success', 'label' => 'Gross Sales', 'value' => money($s['gross_sales'])],
  ['icon' => 'fa-users', 'color' => 'info', 'label' => 'Active Staff', 'value' => number_format($s['unique_staff'])],
  ['icon' => 'fa-credit-card', 'color' => 'warning', 'label' => 'Top Payment', 'value' => e($s['top_payment_method'])],
]])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Sales Volume Trend',
    'id' => 'trendChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'line', 'label' => 'Gross Sales'],
      ['color' => '#17a2b8', 'type' => 'line', 'label' => 'Orders'],
    ],
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Payment Methods',
    'id' => 'methodChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $methodLegend,
    'emptyText' => $data['by_method']->isEmpty() ? 'No payment data in this period.' : null,
  ])
</div>

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Sales by Channel',
    'id' => 'sourceChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $sourceLegend,
    'emptyText' => $data['by_source']->isEmpty() ? 'No channel data in this period.' : null,
  ])
  <div class="col-md-6 mb-3 d-flex">
    <div class="tile mb-0 flex-fill">
      <h3 class="tile-title">Sales by Channel (Details)</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>Channel</th>
                <th class="text-center">Orders</th>
                <th class="money-col">Amount</th>
              </tr>
            </thead>
            <tbody>
              @forelse($data['by_source'] as $src)
              <tr>
                <td><strong>{{ $src['label'] }}</strong></td>
                <td class="text-center">{{ $src['orders'] }}</td>
                <td class="money-col font-weight-bold">{{ money($src['amount']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No channel sales in this period.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Sales by Staff</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>Staff</th>
                <th class="text-center">Orders</th>
                <th class="money-col">Gross</th>
                <th class="money-col">Collected</th>
              </tr>
            </thead>
            <tbody>
              @forelse($staffRows as $staff)
              <tr>
                <td><strong>{{ $staff['name'] }}</strong></td>
                <td class="text-center">{{ $staff['orders'] }}</td>
                <td class="money-col">{{ money($staff['gross']) }}</td>
                <td class="money-col text-success font-weight-bold">{{ money($staff['collected']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">No sales in this period.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($staffRows->hasPages())
        <div class="report-table-footer">
          <p class="text-muted small mb-0">
            Showing {{ $staffRows->firstItem() }}&ndash;{{ $staffRows->lastItem() }} of {{ $staffRows->total() }} staff
          </p>
          {{ $staffRows->links('pagination::bootstrap-4') }}
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

  new Chart(document.getElementById('trendChart').getContext('2d')).Line({
    labels: @json($data['labels']),
    datasets: [
      {
        label: 'Gross Sales',
        fillColor: 'rgba(148,0,0,0.05)',
        strokeColor: '#940000',
        pointColor: '#940000',
        pointStrokeColor: '#fff',
        data: @json($data['gross_trend'])
      },
      {
        label: 'Orders',
        fillColor: 'rgba(23,162,184,0.05)',
        strokeColor: '#17a2b8',
        pointColor: '#17a2b8',
        pointStrokeColor: '#fff',
        data: @json($data['orders_trend'])
      }
    ]
  }, Object.assign({}, baseScaleOptions, {
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    multiTooltipTemplate: '<%= datasetLabel %>: <%= value %>',
  }));

  var methods = @json($data['by_method']);
  var methodColors = @json($methodColors);

  if (methods.length) {
    new Chart(document.getElementById('methodChart').getContext('2d')).Pie(
      methods.map(function (method, index) {
        return {
          value: method.amount,
          color: methodColors[index % methodColors.length],
          highlight: methodColors[index % methodColors.length],
          label: method.label,
        };
      }),
      {
        responsive: true,
        maintainAspectRatio: false,
        segmentShowStroke: false,
        tooltipTemplate: '<%= label %>: TZS <%= value %>',
      }
    );
  }

  var sources = @json($data['by_source']);
  var sourceColors = @json($sourceColors);

  if (sources.length) {
    new Chart(document.getElementById('sourceChart').getContext('2d')).Doughnut(
      sources.map(function (source, index) {
        return {
          value: source.amount,
          color: sourceColors[index % sourceColors.length],
          highlight: sourceColors[index % sourceColors.length],
          label: source.label,
        };
      }),
      {
        responsive: true,
        maintainAspectRatio: false,
        segmentShowStroke: false,
        percentageInnerCutout: 55,
        tooltipTemplate: '<%= label %>: TZS <%= value %>',
      }
    );
  }
})();
</script>
@endsection
