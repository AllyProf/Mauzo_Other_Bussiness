@php
  $plan = $overview['plan'] ?? $business->plan;
  $currentFee = $overview['current_fee'];
  $renewalFee = $overview['renewal_fee'];
  $latestInvoice = $overview['latest_invoice'] ?? null;
  $invoices = $overview['invoices'] ?? collect();
  $monthlyDue = $business->usesProfitShareBilling()
    ? $currentFee['amount']
    : ($currentFee['monthly_equivalent'] ?? $currentFee['amount']);
@endphp

<div class="alert alert-primary text-left mb-4">
  <h5 class="mb-2"><i class="fa fa-money"></i> Amount Due</h5>
  @if($plan)
    <p class="mb-1">
      <strong>This month:</strong> TZS {{ number_format($monthlyDue, 0) }}
      <br><small class="text-muted">{{ $currentFee['label'] }}</small>
    </p>
    <p class="mb-0">
      <strong>To renew subscription:</strong> TZS {{ number_format($renewalFee['amount'], 0) }}
      <br><small class="text-muted">{{ $renewalFee['detail'] ?? $renewalFee['label'] }}</small>
    </p>
  @else
    <p class="mb-0">No subscription plan is assigned. Contact the platform administrator.</p>
  @endif
</div>

@if($plan)
<div class="table-responsive mb-4">
  <table class="table table-bordered text-left mb-0">
    <tr><th style="width:38%;">Plan</th><td>{{ $plan->name ?? '—' }} · {{ $business->billingModelLabel() }}</td></tr>
    <tr><th>Billing</th><td>{{ $business->billingSummary() }}</td></tr>
    <tr>
      <th>Expiry Date</th>
      <td>
        @if($business->expiry_date)
          {{ $business->expiry_date->format('d M, Y') }}
          @if($business->expiry_date->isPast())
            <span class="badge badge-danger ml-1">Expired</span>
          @endif
        @else
          Not set
        @endif
      </td>
    </tr>
    @if($latestInvoice)
    <tr>
      <th>Latest Invoice</th>
      <td>
        {{ $latestInvoice->invoice_number }} · {{ $latestInvoice->billingMonthLabel() }} ·
        TZS {{ number_format((float) $latestInvoice->amount, 0) }} ·
        <span class="badge badge-light border">{{ $latestInvoice->statusLabel() }}</span>
      </td>
    </tr>
    @endif
  </table>
</div>
@endif

@php $paymentInstructions = platform_settings('payment_instructions'); @endphp
@if($paymentInstructions)
<div class="alert alert-light border text-left mb-4">
  <strong><i class="fa fa-money"></i> How to Pay &amp; Renew</strong>
  <div class="mt-2" style="white-space:pre-wrap;">{{ $paymentInstructions }}</div>
</div>
@endif

@if($invoices->isNotEmpty())
<h6 class="text-left font-weight-bold mb-2">Recent Invoices</h6>
<div class="table-responsive mb-3">
  <table class="table table-sm table-bordered text-left mb-0">
    <thead class="thead-light">
      <tr>
        <th>Month</th>
        <th>Invoice</th>
        <th class="text-right">Amount</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoices as $invoice)
      <tr>
        <td>{{ $invoice->billingMonthLabel() }}</td>
        <td>{{ $invoice->invoice_number }}</td>
        <td class="text-right">TZS {{ number_format((float) $invoice->amount, 0) }}</td>
        <td>{{ $invoice->statusLabel() }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif
