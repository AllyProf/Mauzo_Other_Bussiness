@extends('layouts.app')

@section('title', 'Service Sales')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-list-alt"></i> Service Sales</h1>
    <p>
      @if(($shiftContext ?? '') === 'current')
        Service sales for your current shift only
      @elseif(($shiftContext ?? '') === 'none')
        Open a shift to record new service sales
      @elseif($scopedToSelf ?? false)
        Your service sales only
      @else
        All service sales for this business
      @endif
    </p>
  </div>
  <div>
    @if(($requiresOpenShift ?? false) && !($openShift ?? null))
      <a href="{{ route('shifts.create') }}" class="btn btn-warning"><i class="fa fa-clock-o"></i> Open Shift</a>
    @else
      <a href="{{ route('service-pos.create') }}" class="btn btn-success"><i class="fa fa-desktop"></i> Service POS</a>
    @endif
    <a href="{{ route('services.materials') }}" class="btn btn-outline-secondary ml-1"><i class="fa fa-cubes"></i> Materials Stock</a>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('services.categories') }}">Services</a></li>
    <li class="breadcrumb-item">Sales</li>
  </ul>
</div>

@if(($requiresOpenShift ?? false) && !($openShift ?? null))
<div class="alert alert-warning">
  Open a shift before selling services. <a href="{{ route('shifts.create') }}">Open shift</a>
</div>
@elseif($openShift ?? false)
<div class="alert alert-success py-2 mb-3">
  Shift #{{ $openShift->id }} is open. <a href="{{ route('shifts.show', $openShift) }}">View shift</a>
</div>
@endif

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info"><h4>Total Sales</h4><p><b>{{ number_format($stats['total_sales']) }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info"><h4>Gross</h4><p><b>TZS {{ number_format($stats['gross_sales'], 0) }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-check fa-3x"></i>
      <div class="info"><h4>Collected</h4><p><b>TZS {{ number_format($stats['collected'], 0) }}</b></p></div>
    </div>
  </div>
</div>

<div class="tile">
  <div class="tile-body">
    <div class="table-responsive">
      <table class="table table-hover table-bordered">
        <thead>
          <tr>
            <th>{{ __('tables.columns.date') }}</th>
            <th>{{ __('tables.columns.reference') }}</th>
            <th>Services sold</th>
            <th>{{ __('tables.columns.cashier') }}</th>
            <th>{{ __('tables.columns.total') }}</th>
            <th>{{ __('tables.columns.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($sales as $sale)
          <tr>
            <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
            <td>{{ $sale->reference_no }}</td>
            <td>{{ $sale->soldItemsSummary() ?: '—' }}</td>
            <td>{{ $sale->user?->name }}</td>
            <td class="font-weight-bold text-success">{{ money($sale->total_amount) }}</td>
            <td>
              @if($sale->payment_status === 'paid')<span class="badge badge-success">{{ __('tables.status.paid') }}</span>
              @elseif($sale->payment_status === 'cancelled')<span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
              @else<span class="badge badge-warning">{{ ucfirst($sale->payment_status) }}</span>@endif
            </td>
            <td>
              @if(in_array($sale->payment_status, ['pending', 'partial', 'debt']))
                @php
                  $payItems = $sale->items->map(function ($si) {
                      return [
                          'id' => $si->id,
                          'name' => $si->line_description ?: $si->service?->name ?? 'Service',
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
                  data-items='@json($payItems)'><i class="fa fa-money"></i> Pay</button>
                <form action="{{ route('sales.cancel', $sale) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-danger" title="Cancel order"
                    onclick="confirmAction(event, 'Cancel service order?', 'Order {{ $sale->reference_no }} will be cancelled. Materials will be restored if already deducted.')">
                    <i class="fa fa-times"></i>
                  </button>
                </form>
              @endif
              <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary">View</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No service sales yet. Use <a href="{{ route('service-pos.create') }}">Service POS</a> to record a sale.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $sales->appends(request()->query())->links() }}
  </div>
</div>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
<script>
$(function () {
    const autoPaySaleId = @json(request()->query('pay'));
    if (autoPaySaleId) {
        const $payBtn = $('.open-payment-modal-btn[data-sale-id="' + autoPaySaleId + '"]').first();
        if ($payBtn.length) {
            setTimeout(function () { $payBtn.trigger('click'); }, 400);
        }
    }
});
</script>
@include('sales.partials.customer-picker-scripts')
@include('sales.partials.payment-modal-scripts')
@endsection
