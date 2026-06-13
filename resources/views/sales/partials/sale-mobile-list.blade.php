@foreach($sales as $sale)
  @php
    $businessTypeKeys = $sale->items
        ->map(fn ($line) => $line->item?->category?->source_business_type_key ?: 'other')
        ->unique()
        ->values();
    $soldPreview = $sale->soldItemsSummary(2);
    $soldFull = $sale->soldItemsSummary();
    $payItems = $sale->items->map(function ($si) {
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
  <div class="sales-mobile-card" data-business-types="{{ $businessTypeKeys->implode(',') }}">
    <div class="sales-mobile-head">
      <div>
        <div class="sales-mobile-ref">
          {{ $sale->reference_no }}
          @if($sale->isServicePos()) <span class="badge badge-info">{{ __('tables.status.service') }}</span>@endif
        </div>
        <div class="sales-mobile-meta">{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }} · {{ $sale->user->name }}</div>
      </div>
      <div class="text-right">
        <div class="text-success font-weight-bold">{{ money($sale->total_amount) }}</div>
        @if($sale->payment_status == 'paid')
          <span class="badge badge-success">{{ __('tables.status.paid') }}</span>
        @elseif($sale->payment_status == 'partial')
          <span class="badge badge-info">{{ __('tables.status.partial') }}</span>
        @elseif($sale->payment_status == 'debt')
          <span class="badge badge-danger">{{ __('tables.status.debt') }}</span>
        @elseif($sale->payment_status == 'cancelled')
          <span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
        @else
          <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
        @endif
      </div>
    </div>
    @if($soldPreview)
      <div class="sales-mobile-items" @if($soldFull !== $soldPreview) title="{{ $soldFull }}" @endif>{{ $soldPreview }}</div>
    @endif
    <div class="sales-mobile-payment small text-muted">
      @if($sale->payment_status == 'pending')
        Unpaid
      @elseif($sale->payment_status == 'partial')
        Paid: {{ money($sale->amount_paid) }} · Balance: {{ money($sale->total_amount - $sale->amount_paid) }}
        @if($sale->customer_name) · {{ $sale->customer_name }}@endif
      @elseif($sale->payment_status == 'debt')
        Owes: {{ money($sale->total_amount - $sale->amount_paid) }} · {{ $sale->customer_name ?? 'Customer' }}
      @elseif($sale->payment_status == 'cancelled')
        Cancelled
      @else
        {{ ucfirst($sale->payment_method) }}@if($sale->payment_provider) ({{ $sale->payment_provider }})@endif
      @endif
    </div>
    <div class="sales-mobile-actions">
      @if(in_array($sale->payment_status, ['pending', 'partial', 'debt']))
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
          data-items='@json($payItems)'><i class="fa fa-money"></i></button>
        <form action="{{ route('sales.cancel', $sale->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this sale? Stock will be returned.');">
          @csrf
          <button type="submit" class="btn btn-sm btn-danger" title="Cancel Sale"><i class="fa fa-times"></i></button>
        </form>
      @endif
      <a href="{{ route('invoices.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Invoice"><i class="fa fa-file-text-o"></i></a>
      <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-secondary" title="View Receipt"><i class="fa fa-eye"></i></a>
    </div>
  </div>
@endforeach
@if($sales->isEmpty())
  <p class="text-center text-muted py-4 mb-0">
    @if(($shiftContext ?? '') === 'none')
      No active shift. Open a shift to start selling — closed shift sales are listed under <a href="{{ route('shifts.index') }}">Sales Shifts</a>.
    @else
      No sales records found.
    @endif
  </p>
@else
  <p class="text-center text-muted py-3 mb-0 d-none" id="salesMobileNoMatch">No sales match the selected business filter.</p>
@endif
