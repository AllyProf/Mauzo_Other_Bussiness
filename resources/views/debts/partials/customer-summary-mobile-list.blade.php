@foreach($customerSummaries as $customer)
  <div class="debts-summary-card">
    <div class="debts-summary-head">
      <strong>{{ $customer['name'] }}</strong>
      <span class="text-danger font-weight-bold">{{ money($customer['balance']) }}</span>
    </div>
    <div class="debts-summary-meta">
      <span><i class="fa fa-phone"></i> {{ $customer['phone'] ?: '—' }}</span>
      <span>{{ $customer['orders'] }} {{ __('tables.columns.open_orders') }}</span>
    </div>
  </div>
@endforeach
