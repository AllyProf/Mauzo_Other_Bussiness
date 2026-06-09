@foreach($staffRows as $index => $data)
<div class="dc-mobile-card" data-status="{{ $data['status'] }}" data-staff-name="{{ strtolower($data['staff']->name ?? '') }}">
  <div class="dc-mobile-head">
    <div>
      <div class="dc-mobile-title">{{ $data['staff']->name ?? 'Unknown' }}</div>
      @if(!empty($data['staff']->email))
        <div class="dc-mobile-meta">{{ $data['staff']->email }}</div>
      @endif
    </div>
    @if($data['status'] === 'paid' || $data['status'] === 'posted')
      <span class="status-pill badge-success">{{ $data['status'] === 'posted' ? 'Posted' : 'Paid' }}</span>
    @elseif($data['status'] === 'partial')
      <span class="status-pill badge-warning">Partial</span>
    @else
      <span class="status-pill badge-warning">Pending</span>
    @endif
  </div>
  <div class="dc-mobile-grid">
    <div class="dc-mobile-stat">
      <span>Orders</span>
      <strong>{{ $data['total_orders'] }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Gross</span>
      <strong>{{ money($data['gross_sales']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Cash</span>
      <strong>{{ money($data['cash_collected']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Mobile</span>
      <strong>{{ money($data['mobile_collected']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Bank</span>
      <strong>{{ money($data['bank_collected']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Debt paid</span>
      <strong>{{ ($data['debt_collected'] ?? 0) > 0 ? money($data['debt_collected']) : '—' }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Expected</span>
      <strong>{{ money($data['expected_amount']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Collected</span>
      <strong class="text-info">{{ money($data['collected_on_orders']) }}</strong>
    </div>
    <div class="dc-mobile-stat">
      <span>Credit</span>
      <strong class="{{ $data['credit'] > 0 ? 'text-danger' : '' }}">{{ $data['credit'] > 0 ? money($data['credit']) : '—' }}</strong>
    </div>
    @if($showDiff ?? true)
    <div class="dc-mobile-stat">
      <span>Diff</span>
      @php $diff = $data['difference']; @endphp
      <strong class="{{ abs($diff) < 0.01 ? '' : ($diff >= 0 ? 'text-success' : 'text-danger') }}">
        @if(abs($diff) < 0.01)—@else{{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0) }}@endif
      </strong>
    </div>
    @endif
  </div>
</div>
@endforeach
