@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $productRows = $data['productRows'];
  $categoryColors = ['#940000', '#28a745', '#ffc107', '#17a2b8', '#6c757d', '#343a40', '#007bff'];
  $categoryLegend = $data['by_category']->values()->map(function ($cat, $index) use ($categoryColors) {
    return [
      'color' => $categoryColors[$index % count($categoryColors)],
      'type' => 'bar',
      'label' => $cat['category'],
    ];
  })->all();
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-cubes', 'color' => 'primary', 'label' => 'Products Sold', 'value' => number_format($s['products_sold'])],
  ['icon' => 'fa-sort-numeric-asc', 'color' => 'info', 'label' => 'Units Sold', 'value' => number_format($s['units_sold'], 0)],
  ['icon' => 'fa-money', 'color' => 'success', 'label' => 'Total Revenue', 'value' => money($s['total_revenue'])],
  ['icon' => 'fa-line-chart', 'color' => 'warning', 'label' => 'Product Profit', 'value' => money($s['total_profit'])],
]])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Top 10 Products by Revenue',
    'id' => 'productChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'bar', 'label' => 'Revenue'],
    ],
    'emptyText' => empty($data['product_labels']) ? 'No product sales in this period.' : null,
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Revenue by Category',
    'id' => 'categoryChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $categoryLegend,
    'emptyText' => empty($data['category_labels']) ? 'No category sales in this period.' : null,
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Product Performance</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>Product</th>
                <th>{{ __('tables.columns.category') }}</th>
                <th class="text-center">Qty Sold</th>
                <th class="money-col">Revenue</th>
                <th class="money-col">Cost</th>
                <th class="money-col">Profit</th>
              </tr>
            </thead>
            <tbody>
              @forelse($productRows as $p)
              <tr>
                <td><strong>{{ $p['name'] }}</strong></td>
                <td><span class="badge badge-light border">{{ $p['category'] }}</span></td>
                <td class="text-center">{{ number_format($p['qty'], 0) }}</td>
                <td class="money-col">{{ money($p['revenue']) }}</td>
                <td class="money-col text-muted">{{ money($p['cost']) }}</td>
                <td class="money-col text-success font-weight-bold">{{ money($p['profit']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No product sales in this period.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($productRows->hasPages())
        <div class="report-table-footer">
          <p class="text-muted small mb-0">
            Showing {{ $productRows->firstItem() }}&ndash;{{ $productRows->lastItem() }} of {{ $productRows->total() }} products
          </p>
          {{ $productRows->links('pagination::bootstrap-4') }}
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

  var pLabels = @json($data['product_labels']);
  var pData = @json($data['product_revenue']);

  if (pLabels.length) {
    new Chart(document.getElementById('productChart').getContext('2d')).Bar({
      labels: pLabels,
      datasets: [{
        label: 'Revenue',
        fillColor: '#940000',
        strokeColor: '#940000',
        highlightFill: '#b30000',
        highlightStroke: '#940000',
        data: pData
      }]
    }, Object.assign({}, baseScaleOptions, {
      barShowStroke: false,
      tooltipTemplate: '<%if (label){%><%=label%>: <%}%>TZS <%= value %>',
    }));
  }

  var cLabels = @json($data['category_labels']);
  var cData = @json($data['category_revenue']);
  var colors = @json($categoryColors);

  if (cLabels.length) {
    new Chart(document.getElementById('categoryChart').getContext('2d')).Pie(
      cData.map(function (value, index) {
        return {
          value: value,
          color: colors[index % colors.length],
          highlight: colors[index % colors.length],
          label: cLabels[index],
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
})();
</script>
@endsection
