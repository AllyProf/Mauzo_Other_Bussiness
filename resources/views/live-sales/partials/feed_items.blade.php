@forelse($liveFeed as $sale)
@php
  $statusClass = match ($sale->payment_status) {
    'paid' => 'success',
    'cancelled' => 'secondary',
    default => 'warning',
  };
  $statusKey = 'tables.status.'.$sale->payment_status;
  $statusLabel = __($statusKey) !== $statusKey ? __($statusKey) : strtoupper($sale->payment_status);
@endphp
<div class="media border-bottom py-3 px-1 live-feed-item" data-sale-id="{{ $sale->id }}">
  <div class="mr-3 text-center" style="min-width: 52px;">
    <div class="small text-muted">{{ $sale->created_at->format('H:i') }}</div>
    <span class="badge badge-{{ $statusClass }}">{{ strtoupper($statusLabel) }}</span>
  </div>
  <div class="media-body">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <strong>{{ $sale->reference_no }}</strong>
        <span class="text-muted small"> · {{ $sale->user?->name ?? __('live_sales.feed.staff') }}</span>
        @if($sale->customer_name)
        <br><small class="text-muted">{{ $sale->customer_name }}</small>
        @endif
      </div>
      <strong class="text-nowrap ml-2">{{ money($sale->total_amount) }}</strong>
    </div>
    <small class="text-muted d-block mt-1">{{ Str::limit($sale->soldItemsSummary(), 80) }}</small>
    <small class="text-muted">
      {{ $sale->usesServices() ? __('live_sales.feed.service') : __('live_sales.feed.store') }}
      @if($sale->payment_method)
        · {{ active_business()?->paymentMethodLabel($sale->payment_method) ?? $sale->payment_method }}
      @endif
    </small>
  </div>
</div>
@empty
<div class="text-center text-muted py-5">
  <i class="fa fa-shopping-cart fa-2x mb-2"></i>
  <p class="mb-0">{{ __('live_sales.feed.empty') }}</p>
</div>
@endforelse
