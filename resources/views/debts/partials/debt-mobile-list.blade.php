@foreach($debts as $sale)
  @php
    $balance = max(0, $sale->total_amount - $sale->amount_paid);
    $isOverdue = $sale->due_date && $sale->due_date < $today;
    $payItems = $sale->items->map(function ($si) {
        return [
            'id' => $si->id,
            'name' => $si->item->name ?? 'Item',
            'qty' => (float) $si->quantity,
            'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
        ];
    })->values();
  @endphp
  <div class="debts-mobile-card {{ $isOverdue ? 'is-overdue' : '' }}">
    <div class="debts-mobile-head">
      <div>
        <div class="debts-mobile-ref">{{ $sale->reference_no }}</div>
        <div class="debts-mobile-customer">{{ $sale->customer_name ?: 'Walk-in / Unnamed' }}</div>
        <div class="debts-mobile-meta">{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }} · {{ $sale->customer_phone ?: '—' }}</div>
      </div>
      <div class="text-right">
        <div class="text-danger font-weight-bold">{{ money($balance) }}</div>
        @if($sale->payment_status === 'debt')
          <span class="badge badge-danger">{{ __('tables.status.debt') }}</span>
        @elseif($sale->payment_status === 'partial')
          <span class="badge badge-info">{{ __('tables.status.partial') }}</span>
        @else
          <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
        @endif
      </div>
    </div>
    <div class="debts-mobile-grid">
      <div class="debts-mobile-stat"><span>{{ __('tables.columns.sale_total') }}</span><strong>{{ money($sale->total_amount) }}</strong></div>
      <div class="debts-mobile-stat"><span>{{ __('tables.columns.paid') }}</span><strong>{{ money($sale->amount_paid) }}</strong></div>
      <div class="debts-mobile-stat"><span>{{ __('tables.columns.due_date') }}</span><strong>
        @if($sale->due_date)
          {{ \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') }}
          @if($isOverdue)<span class="badge badge-danger ml-1">{{ __('tables.status.overdue') }}</span>@endif
        @else — @endif
      </strong></div>
      <div class="debts-mobile-stat"><span>{{ __('tables.columns.cashier') }}</span><strong>{{ $sale->user->name }}</strong></div>
    </div>
    <div class="debts-mobile-actions">
      <button type="button"
        class="btn btn-sm btn-success open-payment-modal-btn flex-fill"
        data-sale-id="{{ $sale->id }}"
        data-ref="{{ e($sale->reference_no) }}"
        data-total="{{ $sale->total_amount }}"
        data-paid="{{ $sale->amount_paid }}"
        data-customer-name="{{ e($sale->customer_name ?? '') }}"
        data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
        data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
        data-items='@json($payItems)'>
        <i class="fa fa-money"></i> {{ __('tables.actions.collect') }}
      </button>
      <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Receipt"><i class="fa fa-eye"></i></a>
    </div>
  </div>
@endforeach
@if($debts->isEmpty())
  <p class="text-center text-muted py-4 mb-0">{{ __('tables.empty.outstanding_debts') }}</p>
@endif
