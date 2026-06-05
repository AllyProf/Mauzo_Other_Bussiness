@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $tableRows = $data['tableRows'];
  $categoryColors = ['#940000', '#28a745', '#ffc107', '#17a2b8', '#6c757d', '#343a40'];
  $categoryLegend = $data['owner_by_category']->values()->map(function ($cat, $index) use ($categoryColors) {
    return [
      'color' => $categoryColors[$index % count($categoryColors)],
      'type' => 'bar',
      'label' => $cat['label'],
    ];
  })->all();
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-users', 'color' => 'warning', 'label' => 'Staff Expenses', 'value' => money($s['staff_total'])],
  ['icon' => 'fa-briefcase', 'color' => 'danger', 'label' => 'Owner / Petty Cash', 'value' => money($s['owner_total'])],
  ['icon' => 'fa-minus-circle', 'color' => 'primary', 'label' => 'Total Expenses', 'value' => money($s['grand_total'])],
  ['icon' => 'fa-exchange', 'color' => 'info', 'label' => 'From Circulation', 'value' => money($data['owner_by_fund']['circulation'])],
]])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Daily Expense Trend',
    'id' => 'expenseChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#ffc107', 'type' => 'line', 'label' => 'Staff Expenses'],
      ['color' => '#dc3545', 'type' => 'line', 'label' => 'Owner Expenses'],
    ],
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Owner Expenses by Category',
    'id' => 'categoryChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $categoryLegend,
    'emptyText' => $data['owner_by_category']->isEmpty() ? 'No owner expenses by category in this period.' : null,
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <div class="tile-title-w-btn">
        <h3 class="title">Expense Summary</h3>
        <p class="mb-0 text-muted small">
          Owner profit expenses: <strong>{{ money($data['owner_by_fund']['profit']) }}</strong>
        </p>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                <th class="money-col">Staff Expenses</th>
                <th class="money-col">Owner Expenses</th>
                <th class="money-col">Daily Total</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tableRows as $row)
              <tr>
                <td><strong>{{ $row['date_label'] }}</strong></td>
                <td class="money-col">{{ money($row['staff']) }}</td>
                <td class="money-col">{{ money($row['owner']) }}</td>
                <td class="money-col font-weight-bold">{{ money($row['total']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">No expenses recorded in this period.</td>
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

  new Chart(document.getElementById('expenseChart').getContext('2d')).Line({
    labels: @json($data['labels']),
    datasets: [
      {
        label: 'Staff Expenses',
        fillColor: 'rgba(255,193,7,0.05)',
        strokeColor: '#ffc107',
        pointColor: '#ffc107',
        pointStrokeColor: '#fff',
        data: @json($data['staff'])
      },
      {
        label: 'Owner Expenses',
        fillColor: 'rgba(220,53,69,0.05)',
        strokeColor: '#dc3545',
        pointColor: '#dc3545',
        pointStrokeColor: '#fff',
        data: @json($data['owner'])
      }
    ]
  }, Object.assign({}, baseScaleOptions, {
    datasetFill: false,
    bezierCurve: false,
    pointDotRadius: 4,
    datasetStrokeWidth: 2,
    multiTooltipTemplate: '<%= datasetLabel %>: TZS <%= value %>',
  }));

  var catLabels = @json($data['owner_by_category']->pluck('label'));
  var catData = @json($data['owner_by_category']->pluck('amount'));
  var colors = @json($categoryColors);

  if (catLabels.length) {
    new Chart(document.getElementById('categoryChart').getContext('2d')).Doughnut(
      catData.map(function (value, index) {
        return {
          value: value,
          color: colors[index % colors.length],
          highlight: colors[index % colors.length],
          label: catLabels[index],
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
