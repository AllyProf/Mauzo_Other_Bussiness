@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $tableRows = $data['tableRows'];
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-shopping-bag', 'color' => 'primary', 'label' => 'Total Orders', 'value' => number_format($s['total_orders'])],
  ['icon' => 'fa-money', 'color' => 'success', 'label' => 'Gross Sales', 'value' => money($s['gross_sales'])],
  ['icon' => 'fa-check-circle', 'color' => 'info', 'label' => 'Collected', 'value' => money($s['collected'])],
  ['icon' => 'fa-calculator', 'color' => 'warning', 'label' => 'Avg Order Value', 'value' => money($s['avg_order_value'] ?? 0)],
]])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Daily Sales & Collections',
    'id' => 'salesChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'line', 'label' => 'Gross Sales'],
      ['color' => '#28a745', 'type' => 'line', 'label' => 'Collected'],
    ],
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Orders per Day',
    'id' => 'ordersChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'bar', 'label' => 'Orders'],
    ],
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Daily Sales Table</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th class="text-center">Orders</th>
                <th class="money-col">Gross Sales</th>
                <th class="money-col">Collected</th>
                <th class="money-col">Outstanding</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tableRows as $row)
              <tr>
                <td><strong>{{ $row['date_label'] }}</strong></td>
                <td class="text-center">{{ $row['orders'] }}</td>
                <td class="money-col">{{ money($row['gross']) }}</td>
                <td class="money-col text-success font-weight-bold">{{ money($row['collected']) }}</td>
                <td class="money-col text-warning">{{ money($row['outstanding']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">No daily sales records found for this period.</td>
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
  var labels = @json($data['labels']);

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

  new Chart(document.getElementById('salesChart').getContext('2d')).Line({
    labels: labels,
    datasets: [
      {
        label: 'Gross Sales',
        fillColor: 'rgba(148,0,0,0.05)',
        strokeColor: '#940000',
        pointColor: '#940000',
        pointStrokeColor: '#fff',
        data: @json($data['gross'])
      },
      {
        label: 'Collected',
        fillColor: 'rgba(40,167,69,0.05)',
        strokeColor: '#28a745',
        pointColor: '#28a745',
        pointStrokeColor: '#fff',
        data: @json($data['collected'])
      }
    ]
  }, Object.assign({}, baseScaleOptions, {
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    multiTooltipTemplate: '<%= datasetLabel %>: TZS <%= value %>',
  }));

  new Chart(document.getElementById('ordersChart').getContext('2d')).Bar({
    labels: labels,
    datasets: [{
      label: 'Orders',
      fillColor: '#940000',
      strokeColor: '#940000',
      highlightFill: '#b30000',
      highlightStroke: '#940000',
      data: @json($data['orders'])
    }]
  }, Object.assign({}, baseScaleOptions, {
    barShowStroke: false,
    scaleIntegersOnly: true,
    tooltipTemplate: '<%if (label){%><%=label%>: <%}%><%= value %> orders',
  }));
})();
</script>
@endsection
