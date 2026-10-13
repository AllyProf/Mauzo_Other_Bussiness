@php
  $canPay = in_array($sale->payment_status, ['pending', 'partial', 'debt'], true);
  $payItems = $payItems ?? $sale->items->map(function ($si) {
      return [
          'id' => $si->id,
          'name' => $si->service_id
              ? ($si->line_description ?: $si->service?->name ?? 'Service')
              : ($si->item->name ?? 'Item'),
          'qty' => (float) $si->quantity,
          'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
      ];
  })->values();
@endphp
<div class="sale-row-actions">
  @if($canPay)
    <button type="button"
      class="btn btn-sm btn-success open-payment-modal-btn sale-action-pay"
      title="Record payment"
      data-sale-id="{{ $sale->id }}"
      data-ref="{{ e($sale->reference_no) }}"
      data-total="{{ $sale->total_amount }}"
      data-paid="{{ $sale->amount_paid }}"
      data-customer-id="{{ $sale->customer_id ?? '' }}"
      data-customer-name="{{ e($sale->customer_name ?? '') }}"
      data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
      data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
      data-items='@json($payItems)'>
      <i class="fa fa-money"></i> Pay
    </button>
  @endif

  <div class="btn-group sale-actions-more">
    <button type="button"
            class="btn btn-sm btn-outline-secondary sale-actions-menu-btn dropdown-toggle"
            data-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false"
            title="More actions">
      <i class="fa fa-ellipsis-v"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-right sale-actions-dropdown">
      <a class="dropdown-item" href="{{ route('invoices.show', $sale->id) }}">
        <i class="fa fa-file-text-o fa-fw text-muted"></i> Invoice
      </a>
      <a class="dropdown-item" href="{{ route('sales.show', $sale->id) }}">
        <i class="fa fa-eye fa-fw text-muted"></i> Receipt
      </a>
      @if($canPay)
        <div class="dropdown-divider"></div>
        <form action="{{ route('sales.cancel', $sale->id) }}" method="POST" class="sale-cancel-form" onsubmit="return confirm('Cancel this sale? Stock will be returned.');">
          @csrf
          <button type="submit" class="dropdown-item text-danger">
            <i class="fa fa-times fa-fw"></i> Cancel sale
          </button>
        </form>
      @endif
    </div>
  </div>
</div>
