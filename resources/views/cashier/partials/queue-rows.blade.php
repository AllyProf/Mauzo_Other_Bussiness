@forelse($sales as $sale)
  @php
    $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
    $payItems = $sale->items->map(fn ($si) => [
        'id' => $si->id,
        'name' => $si->service_id
            ? ($si->line_description ?: $si->service?->name ?? 'Service')
            : ($si->item->name ?? 'Item'),
        'qty' => (float) $si->quantity,
        'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
    ])->values();
    $itemsSummary = $payItems->take(3)->map(fn ($i) => $i['name'].' × '.rtrim(rtrim(number_format($i['qty'], 2), '0'), '.'))->implode(', ');
    $waiting = $sale->created_at ? $sale->created_at->diffForHumans(null, true) : '';
  @endphp
  <tr data-sale-id="{{ $sale->id }}" class="queue-row">
    <td>
      <strong>{{ $sale->reference_no }}</strong>
      @if(in_array($sale->sale_source, ['service_pos', 'service_invoice'], true))
        <span class="badge badge-info">Service</span>
      @endif
      <div class="small text-muted">{{ $sale->created_at?->format('M d, h:i A') }} · {{ $waiting }} ago</div>
    </td>
    <td>{{ $sale->user?->name ?? '—' }}</td>
    <td>
      {{ $sale->customer_name ?: 'Walk-in' }}
      @if($sale->customer_phone)<div class="small text-muted">{{ $sale->customer_phone }}</div>@endif
    </td>
    <td class="small">
      {{ $itemsSummary }}@if($payItems->count() > 3) <span class="text-muted">+{{ $payItems->count() - 3 }} more</span>@endif
    </td>
    <td class="text-right">{{ money($sale->total_amount) }}</td>
    @if(($tab ?? 'unpaid') === 'paid')
      @php
        $payments = $sale->payments->sortBy('created_at');
        $lastPayment = $payments->last();
      @endphp
      <td>{{ $payments->map(fn ($p) => $p->user?->name)->filter()->unique()->implode(', ') ?: '—' }}</td>
      <td class="small">
        @foreach($payments as $p)
          <div>{{ $p->payment_provider ?: ucfirst(str_replace('_', ' ', $p->payment_method)) }} — {{ money($p->amount) }}</div>
        @endforeach
      </td>
      <td class="text-center small">{{ $lastPayment?->created_at?->format('M d, h:i A') ?? '—' }}</td>
      <td class="text-center text-nowrap">
        <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-secondary" title="View Receipt"><i class="fa fa-eye"></i></a>
        <a href="{{ route('sales.show', ['sale' => $sale->id, 'print' => 1]) }}" target="_blank" class="btn btn-sm btn-info" title="Print Receipt"><i class="fa fa-print"></i></a>
      </td>
    @else
    <td class="text-right">{{ money($sale->amount_paid) }}</td>
    <td class="text-right"><strong class="text-danger">{{ money($balance) }}</strong></td>
    <td class="text-center">
      @if($sale->payment_status === 'partial')
        <span class="badge badge-warning">Partial</span>
      @else
        <span class="badge badge-secondary">Awaiting payment</span>
      @endif
    </td>
    @php
      $lock = ($locks ?? [])[$sale->id] ?? null;
      $lockedByOther = $lock && (int) $lock['user_id'] !== (int) auth()->id();
    @endphp
    <td class="text-center text-nowrap">
      @if($lockedByOther)
        <span class="badge badge-warning d-block mb-1"><i class="fa fa-lock"></i> {{ $lock['name'] }} collecting</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ $lock['name'] }} is collecting this payment"><i class="fa fa-lock"></i></button>
      @else
      <button type="button"
        class="btn btn-sm btn-success open-payment-modal-btn"
        title="Collect Payment"
        data-sale-id="{{ $sale->id }}"
        data-ref="{{ e($sale->reference_no) }}"
        data-total="{{ $sale->total_amount }}"
        data-paid="{{ $sale->amount_paid }}"
        data-customer-id="{{ $sale->customer_id ?? '' }}"
        data-customer-name="{{ e($sale->customer_name ?? '') }}"
        data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
        data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
        data-items='@json($payItems)'><i class="fa fa-money"></i></button>
      @endif
      <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-secondary" title="View Order"><i class="fa fa-eye"></i></a>
    </td>
    @endif
  </tr>
@empty
  <tr>
    <td colspan="9" class="text-center text-muted py-4">
      @if(($tab ?? 'unpaid') === 'paid')
        <i class="fa fa-inbox fa-2x d-block mb-2"></i>
        No paid orders found for this day.
      @else
        <i class="fa fa-check-circle fa-2x text-success d-block mb-2"></i>
        No orders waiting for payment.
      @endif
    </td>
  </tr>
@endforelse
