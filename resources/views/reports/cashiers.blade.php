@extends('reports._layout')

@section('report-content')
@php
  $s = $data['summary'];
  $rows = collect($data['rows']);
  $fmtWait = fn ($m) => $m === null ? '—' : ($m >= 60 ? floor($m / 60).'h '.round(fmod($m, 60)).'m' : round($m).' min');
@endphp

@include('reports.partials.stat-widgets', ['widgets' => [
  ['icon' => 'fa-money', 'color' => 'success', 'label' => 'Collected by Cashiers', 'value' => money($s['cashier_collected'])],
  ['icon' => 'fa-pie-chart', 'color' => 'primary', 'label' => 'Cashier Share of Collections', 'value' => $s['cashier_share_percent'].'%'],
  ['icon' => 'fa-clock-o', 'color' => 'info', 'label' => 'Avg Customer Wait', 'value' => $fmtWait($s['avg_wait_minutes'])],
  ['icon' => 'fa-exclamation-triangle', 'color' => 'danger', 'label' => 'Handover Short / Over', 'value' => money($s['total_short']).' / '.money($s['total_over'])],
]])

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-0">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h3 class="tile-title mb-2">Collections by Staff</h3>
        <div class="btn-group btn-group-sm mb-2">
          <a href="{{ request()->fullUrlWithQuery(['cashiers_only' => null]) }}" class="btn {{ request()->boolean('cashiers_only') ? 'btn-outline-secondary' : 'btn-secondary' }}">All collectors</a>
          <a href="{{ request()->fullUrlWithQuery(['cashiers_only' => 1]) }}" class="btn {{ request()->boolean('cashiers_only') ? 'btn-secondary' : 'btn-outline-secondary' }}">Cashiers only</a>
        </div>
      </div>
      <p class="small text-muted">
        <strong>Counter orders</strong> are orders a person collected on but did not sell.
        <strong>Wait</strong> is the time from when the order was placed to the first payment at the counter.
      </p>
      <div class="table-responsive">
        <table class="table table-hover table-bordered report-table mb-0">
          <thead>
            <tr>
              <th>Staff</th>
              <th class="text-center">Payments</th>
              <th class="text-center">Counter Orders</th>
              <th class="money-col">Cash</th>
              <th class="money-col">Mobile / Bank</th>
              <th class="money-col">Total</th>
              <th class="text-center">Avg Wait</th>
              <th class="text-center">Longest Wait</th>
              <th class="text-center">Shifts / Handovers</th>
              <th class="money-col">Short</th>
              <th class="money-col">Over</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $row)
              <tr>
                <td>
                  <strong>{{ $row['name'] }}</strong>
                  <span class="badge {{ $row['is_cashier'] ? 'badge-success' : 'badge-secondary' }}">{{ $row['role_label'] }}</span>
                  @if($row['branch'])<div class="small text-muted">{{ $row['branch'] }}</div>@endif
                  @if(! empty($row['by_method']))
                    <div class="small text-muted">
                      {{ collect($row['by_method'])->map(fn ($m) => $m['label'].' '.money($m['amount']))->implode(' · ') }}
                    </div>
                  @endif
                </td>
                <td class="text-center">{{ number_format($row['payments_count']) }}</td>
                <td class="text-center">{{ number_format($row['counter_orders']) }}</td>
                <td class="money-col">{{ money($row['cash']) }}</td>
                <td class="money-col">{{ money($row['non_cash']) }}</td>
                <td class="money-col font-weight-bold text-success">{{ money($row['total']) }}</td>
                <td class="text-center">{{ $fmtWait($row['avg_wait_minutes']) }}</td>
                <td class="text-center">{{ $fmtWait($row['max_wait_minutes']) }}</td>
                <td class="text-center">{{ $row['shifts'] }} / {{ $row['handovers'] }}</td>
                <td class="money-col {{ $row['money_short'] > 0 ? 'text-danger font-weight-bold' : '' }}">{{ money($row['money_short']) }}</td>
                <td class="money-col {{ $row['money_over'] > 0 ? 'text-warning font-weight-bold' : '' }}">{{ money($row['money_over']) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center text-muted py-4">No payments collected in this period.</td>
              </tr>
            @endforelse
          </tbody>
          @if($rows->isNotEmpty())
          <tfoot>
            <tr class="font-weight-bold">
              <td>Total</td>
              <td class="text-center">{{ number_format($rows->sum('payments_count')) }}</td>
              <td class="text-center">{{ number_format($rows->sum('counter_orders')) }}</td>
              <td class="money-col">{{ money($rows->sum('cash')) }}</td>
              <td class="money-col">{{ money($rows->sum('non_cash')) }}</td>
              <td class="money-col">{{ money($rows->sum('total')) }}</td>
              <td colspan="3"></td>
              <td class="money-col">{{ money($rows->sum('money_short')) }}</td>
              <td class="money-col">{{ money($rows->sum('money_over')) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
