@foreach($sales as $sale)
    @php
      $businessTypeKeys = $sale->items
          ->map(function ($line) {
              if ($line->service_id) {
                  return $line->service?->category?->source_service_type_key ?: 'other';
              }

              return $line->item?->category?->source_business_type_key ?: 'other';
          })
          ->unique()
          ->values();
      $isCarriedOver = ($openShift ?? null)
          && (int) $sale->shift_id !== (int) $openShift->id
          && in_array($sale->payment_status, ['pending', 'partial', 'debt'], true);
      $hasServiceLines = $sale->items->contains(fn ($line) => ! empty($line->service_id));
      $hasProductLines = $sale->items->contains(fn ($line) => ! empty($line->item_id));
    @endphp
    <tr data-business-types="{{ $businessTypeKeys->implode(',') }}">
        <td data-order="{{ $sale->id }}">{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
        <td>
          {{ $sale->reference_no }}
          @if($sale->isServicePos() || ($hasServiceLines && ! $hasProductLines))
            <span class="badge badge-info">Service</span>
          @elseif($hasServiceLines && $hasProductLines)
            <span class="badge badge-secondary">Mixed</span>
          @endif
          @if($isCarriedOver)
            <span class="badge badge-warning" title="Unpaid from a previous shift">Shift #{{ $sale->shift_id }}</span>
          @endif
        </td>
        <td class="sold-items-cell">
          @php
            $soldPreview = $sale->soldItemsSummary(2);
            $soldFull = $sale->soldItemsSummary();
          @endphp
          @if($soldPreview)
            <span class="text-dark sold-items-preview" @if($soldFull !== $soldPreview) title="{{ $soldFull }}" @endif>{{ $soldPreview }}</span>
          @else
            <span class="text-muted">—</span>
          @endif
        </td>
        <td>{{ $sale->user->name }}</td>
        <td class="text-success font-weight-bold">{{ money($sale->total_amount) }}</td>
        <td>
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
        </td>
        <td>
            @if($sale->payment_status == 'pending')
                <span class="text-muted">Unpaid</span>
            @elseif($sale->payment_status == 'partial')
                Paid: {{ money($sale->amount_paid) }}<br>
                <small class="text-danger">Balance: {{ money($sale->total_amount - $sale->amount_paid) }}</small>
                @if($sale->customer_name)
                    <br><small>{{ $sale->customer_name }}</small>
                @endif
                @if($sale->due_date)
                    <br><small>Due: {{ \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') }}</small>
                @endif
            @elseif($sale->payment_status == 'debt')
                <span class="text-danger">Owes: {{ money($sale->total_amount - $sale->amount_paid) }}</span><br>
                {{ $sale->customer_name ?? 'Customer' }}
                (Due: {{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') : 'Not set' }})
            @elseif($sale->payment_status == 'cancelled')
                <span class="text-muted">-</span>
            @else
                {{ ucfirst($sale->payment_method) }} 
                @if($sale->payment_provider)
                    ({{ $sale->payment_provider }})
                @endif
            @endif
        </td>
        <td class="text-nowrap">
            @if(in_array($sale->payment_status, ['pending', 'partial', 'debt']))
                @php
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

                <form action="{{ route('sales.cancel', $sale->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to cancel this sale? Stock will be returned.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger" title="Cancel Sale"><i class="fa fa-times"></i></button>
                </form>
            @endif
            <a href="{{ route('invoices.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Invoice"><i class="fa fa-file-text-o"></i></a>
            <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-secondary" title="View Receipt"><i class="fa fa-eye"></i></a>
        </td>
    </tr>
@endforeach
@if($sales->isEmpty())
    <tr>
        <td colspan="8" class="text-center py-4 text-muted">
          @if(($shiftContext ?? '') === 'none' && !($showAllHistory ?? false))
            No active shift. Open a shift to start selling — closed shift sales are listed under <a href="{{ route('shifts.index') }}">Sales Shifts</a>.
          @else
            No sales records found.
          @endif
        </td>
    </tr>
@endif
