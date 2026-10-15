@php
  $shift = $sale->shift;
@endphp
<div class="sales-shift-separator-mobile">
  @if($sale->shift_id && $shift)
    <i class="fa fa-clock-o"></i> Shift #{{ $shift->id }}
    · {{ $shift->opened_at?->format('M d, h:i A') ?? '—' }}
    @if($shift->status === 'open')
      <span class="badge badge-success">Open</span>
    @endif
  @else
    <i class="fa fa-minus-circle"></i> {{ __('pages.sales.no_shift_sales') }}
  @endif
</div>
