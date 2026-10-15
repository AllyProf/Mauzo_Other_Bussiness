@php
  $shift = $sale->shift;
@endphp
<tr class="sales-shift-separator">
  <td colspan="8">
    @if($sale->shift_id && $shift)
      <i class="fa fa-clock-o text-muted"></i>
      <strong>Shift #{{ $shift->id }}</strong>
      <span class="text-muted">· Opened {{ $shift->opened_at?->format('M d, Y h:i A') ?? '—' }}</span>
      @if($shift->status === 'open')
        <span class="badge badge-success ml-1">Open</span>
      @elseif($shift->closed_at)
        <span class="text-muted small">· Closed {{ $shift->closed_at->format('M d, h:i A') }}</span>
      @endif
    @else
      <i class="fa fa-minus-circle text-muted"></i>
      <strong>{{ __('pages.sales.no_shift_sales') }}</strong>
    @endif
  </td>
</tr>
