@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $debtorRows = $data['debtorRows'];
  $agingColors = [
    'current' => '#28a745',
    '1_30' => '#ffc107',
    '31_60' => '#fd7e14',
    '61_90' => '#dc3545',
    '90_plus' => '#940000',
    'no_due_date' => '#6c757d',
  ];
  $agingLegend = collect($data['aging'])
    ->filter(fn ($bucket) => $bucket['amount'] > 0)
    ->map(fn ($bucket, $key) => [
      'color' => $agingColors[$key] ?? '#6c757d',
      'type' => 'bar',
      'label' => $bucket['label'],
    ])
    ->values()
    ->all();
  $hasAgingChart = !empty($agingLegend);
  $agingChartData = collect($data['aging'])
    ->filter(fn ($bucket) => $bucket['amount'] > 0)
    ->map(fn ($bucket, $key) => [
      'label' => $bucket['label'],
      'amount' => round($bucket['amount'], 2),
      'color' => $agingColors[$key] ?? '#6c757d',
    ])
    ->values()
    ->all();
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-money', 'color' => 'danger', 'label' => 'Total Outstanding', 'value' => money($s['total_outstanding'])],
  ['icon' => 'fa-file-text-o', 'color' => 'primary', 'label' => 'Open Accounts', 'value' => number_format($s['open_accounts'])],
  ['icon' => 'fa-clock-o', 'color' => 'warning', 'label' => 'Overdue', 'value' => number_format($s['overdue_count'])],
  ['icon' => 'fa-check-circle', 'color' => 'success', 'label' => 'Collected (Period)', 'value' => money($s['collected_in_period'])],
]])

<div class="row report-chart-row">
  @include('reports.partials.chart-tile', [
    'title' => 'Debt Aging Analysis',
    'id' => 'agingChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => $agingLegend,
    'emptyText' => !$hasAgingChart ? 'No outstanding debt to age.' : null,
  ])
  @include('reports.partials.chart-tile', [
    'title' => 'Top Debtors',
    'id' => 'debtorsChart',
    'cols' => 6,
    'fixedHeight' => 280,
    'legendItems' => [
      ['color' => '#940000', 'type' => 'bar', 'label' => 'Outstanding Balance'],
    ],
    'emptyText' => $data['top_debtors']->isEmpty() ? 'No outstanding debts.' : null,
  ])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <h3 class="tile-title">Top Debtors</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.customer') }}</th>
                <th class="text-center">Orders</th>
                <th class="money-col">Balance</th>
              </tr>
            </thead>
            <tbody>
              @forelse($debtorRows as $c)
              <tr>
                <td><strong>{{ $c['name'] }}</strong></td>
                <td class="text-center">
                  @if(($c['orders'] ?? 0) > 0)
                  <button type="button"
                    class="btn btn-link btn-sm p-0 debtor-orders-btn font-weight-bold"
                    data-customer="{{ e($c['name']) }}"
                    data-sales='@json($c['sales'] ?? [])'
                    title="View outstanding orders">
                    {{ $c['orders'] }}
                  </button>
                  @else
                  0
                  @endif
                </td>
                <td class="money-col text-danger font-weight-bold">{{ money($c['balance']) }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No outstanding debts.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($debtorRows->hasPages())
        <div class="report-table-footer">
          <p class="text-muted small mb-0">
            Showing {{ $debtorRows->firstItem() }}&ndash;{{ $debtorRows->lastItem() }} of {{ $debtorRows->total() }} customers
          </p>
          {{ $debtorRows->links('pagination::bootstrap-4') }}
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <div class="tile-title-w-btn">
        <h3 class="title">Aging Breakdown</h3>
        <p>
          <a href="{{ route('debts.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-external-link"></i> Manage Debts</a>
        </p>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>Aging Bucket</th>
                <th class="text-center">Accounts</th>
                <th class="money-col">Amount</th>
              </tr>
            </thead>
            <tbody>
              @php $hasAging = false; @endphp
              @foreach($data['aging'] as $bucket)
                @if($bucket['count'] > 0)
                  @php $hasAging = true; @endphp
                  <tr>
                    <td>{{ $bucket['label'] }}</td>
                    <td class="text-center">{{ $bucket['count'] }}</td>
                    <td class="money-col font-weight-bold">{{ money($bucket['amount']) }}</td>
                  </tr>
                @endif
              @endforeach
              @unless($hasAging)
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No outstanding debt to age.</td>
              </tr>
              @endunless
            </tbody>
          </table>
        </div>
        <p class="text-muted small mb-0 mt-3">
          <i class="fa fa-info-circle"></i>
          New debt created in period: <strong>{{ money($s['new_debt_in_period']) }}</strong>
        </p>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="debtorOrdersModal" tabindex="-1" role="dialog" aria-labelledby="debtorOrdersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="debtorOrdersModalLabel">Outstanding orders</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <p class="px-3 pt-3 mb-2 text-muted small" id="debtorOrdersModalCustomer"></p>
        <div class="table-responsive">
          <table class="table table-hover table-bordered report-table mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.reference') }}</th>
                <th>Date</th>
                <th>{{ __('tables.columns.due_date') }}</th>
                <th class="money-col">{{ __('tables.columns.sale_total') }}</th>
                <th class="money-col">{{ __('tables.columns.paid') }}</th>
                <th class="money-col">Balance</th>
                <th class="text-center d-print-none" style="width: 4rem;"></th>
              </tr>
            </thead>
            <tbody id="debtorOrdersModalBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('report-scripts')
<script>
(function () {
  var currencyLabel = @json(config('app.currency_code', 'TZS'));

  function formatMoney(amount) {
    var n = Number(amount) || 0;
    return currencyLabel + ' ' + n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function formatDate(iso) {
    if (!iso) return '—';
    var parts = iso.split('-');
    if (parts.length !== 3) return iso;
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10) + ', ' + parts[0];
  }

  document.querySelectorAll('.debtor-orders-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var customer = btn.getAttribute('data-customer') || '';
      var sales = [];
      try {
        sales = JSON.parse(btn.getAttribute('data-sales') || '[]');
      } catch (e) {
        sales = [];
      }

      document.getElementById('debtorOrdersModalLabel').textContent = 'Outstanding orders';
      document.getElementById('debtorOrdersModalCustomer').textContent = customer;

      var tbody = document.getElementById('debtorOrdersModalBody');
      tbody.innerHTML = '';

      if (!sales.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No orders found.</td></tr>';
      } else {
        sales.forEach(function (sale) {
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td><strong>' + (sale.reference_no || '—') + '</strong></td>' +
            '<td>' + formatDate(sale.date) + '</td>' +
            '<td>' + formatDate(sale.due_date) + '</td>' +
            '<td class="money-col">' + formatMoney(sale.total) + '</td>' +
            '<td class="money-col">' + formatMoney(sale.paid) + '</td>' +
            '<td class="money-col text-danger font-weight-bold">' + formatMoney(sale.balance) + '</td>' +
            '<td class="text-center"><a href="' + (sale.show_url || '#') + '" class="btn btn-sm btn-primary" title="View receipt"><i class="fa fa-eye"></i></a></td>';
          tbody.appendChild(tr);
        });
      }

      if (window.jQuery) {
        jQuery('#debtorOrdersModal').modal('show');
      } else {
        document.getElementById('debtorOrdersModal').classList.add('show');
        document.getElementById('debtorOrdersModal').style.display = 'block';
      }
    });
  });
})();
</script>
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

  var agingChartData = @json($agingChartData);

  if (agingChartData.length) {
    new Chart(document.getElementById('agingChart').getContext('2d')).Doughnut(
      agingChartData.map(function (item) {
        return {
          value: item.amount,
          color: item.color,
          highlight: item.color,
          label: item.label,
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

  var debtors = @json($data['top_debtors']);

  if (debtors.length) {
    new Chart(document.getElementById('debtorsChart').getContext('2d')).Bar({
      labels: debtors.map(function (debtor) {
        return debtor.name.length > 18 ? debtor.name.substring(0, 18) + '…' : debtor.name;
      }),
      datasets: [{
        label: 'Outstanding Balance',
        fillColor: '#940000',
        strokeColor: '#940000',
        highlightFill: '#b30000',
        highlightStroke: '#940000',
        data: debtors.map(function (debtor) { return debtor.balance; }),
      }],
    }, Object.assign({}, baseScaleOptions, {
      barShowStroke: false,
      tooltipTemplate: '<%if (label){%><%=label%>: <%}%>TZS <%= value %>',
    }));
  }
})();
</script>
@endsection
