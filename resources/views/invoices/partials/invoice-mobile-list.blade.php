@foreach($sales as $sale)
  @php
    $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
    $canPay = in_array($sale->payment_status, ['pending', 'partial', 'debt']) && $balanceDue > 0;
    $payItems = $sale->items->map(fn ($si) => [
      'id' => $si->id,
      'name' => $si->item->name ?? 'Item',
      'qty' => (float) $si->quantity,
      'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
    ])->values();
  @endphp
  <div class="inv-mobile-card">
    <div class="inv-mobile-head">
      <div>
        <div class="inv-mobile-ref">{{ $sale->reference_no }}</div>
        <div class="inv-mobile-meta">{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }} · {{ $sale->user->name }}</div>
      </div>
      <div class="text-right">
        <div class="text-success font-weight-bold">{{ money($sale->total_amount) }}</div>
        @if($balanceDue > 0 && $sale->amount_paid > 0)
          <small class="text-danger d-block">Due: {{ money($balanceDue) }}</small>
        @endif
        @if($sale->payment_status == 'paid')
          <span class="badge badge-success">{{ __('tables.status.paid') }}</span>
        @elseif($sale->payment_status == 'partial')
          <span class="badge badge-info">{{ __('tables.status.partial') }}</span>
        @elseif($sale->payment_status == 'debt')
          <span class="badge badge-danger">Credit</span>
        @else
          <span class="badge badge-warning">Unpaid</span>
        @endif
      </div>
    </div>
    <div class="inv-mobile-customer">
      @if($sale->customer_name)
        <strong>{{ $sale->customer_name }}</strong>
        @if($sale->customer_phone)
          <span class="text-muted"> · {{ $sale->customer_phone }}</span>
        @endif
      @else
        <span class="text-muted">Walk-in</span>
      @endif
    </div>
    <div class="inv-mobile-actions">
      <a href="{{ route('invoices.show', $sale) }}" class="btn btn-sm btn-primary" title="View / Print Invoice"><i class="fa fa-file-text-o"></i> Invoice</a>
      @if($canPay)
        <button type="button"
          class="btn btn-sm btn-success open-payment-modal-btn"
          title="Record Payment"
          data-sale-id="{{ $sale->id }}"
          data-ref="{{ e($sale->reference_no) }}"
          data-total="{{ $sale->total_amount }}"
          data-paid="{{ $sale->amount_paid }}"
          data-customer-id="{{ $sale->customer_id ?? '' }}"
          data-customer-name="{{ e($sale->customer_name ?? '') }}"
          data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
          data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
          data-items='@json($payItems)'><i class="fa fa-money"></i> Pay</button>
      @endif
      @if($sale->payment_status === 'paid' || (float) $sale->amount_paid > 0)
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-info" title="View / Print Receipt"><i class="fa fa-print"></i></a>
      @else
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-secondary" title="View Receipt"><i class="fa fa-eye"></i></a>
      @endif
    </div>
  </div>
@endforeach
