@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-file-text-o"></i> Invoices</h1>
    <p>
      @if($scopedToSelf ?? false)
        Your invoices only · POS orders are under Store / POS
      @else
        Formal invoices only · POS orders (including partial pay) stay under Store / POS
      @endif
    </p>
  </div>
  @can('process_sales')
    <a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create Invoice</a>
  @endcan
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-file-text-o fa-3x"></i>
      <div class="info">
        <h4>Total Invoices</h4>
        <p><b>{{ number_format($stats['total']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>Unpaid / Credit</h4>
        <p><b>{{ number_format($stats['unpaid']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>Invoice Value</h4>
        <p><b>{{ money($stats['total_amount']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        @if($sales->count() > 0)
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Invoice No.</th>
              <th>Customer</th>
              <th>Cashier</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
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
              <tr>
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
                <td><strong>{{ $sale->reference_no }}</strong></td>
                <td>
                  @if($sale->customer_name)
                    {{ $sale->customer_name }}
                    @if($sale->customer_phone)
                      <br><small class="text-muted">{{ $sale->customer_phone }}</small>
                    @endif
                  @else
                    <span class="text-muted">Walk-in</span>
                  @endif
                </td>
                <td>{{ $sale->user->name }}</td>
                <td>
                  <span class="text-success font-weight-bold">{{ money($sale->total_amount) }}</span>
                  @if($balanceDue > 0 && $sale->amount_paid > 0)
                    <br><small class="text-danger">Due: {{ money($balanceDue) }}</small>
                  @endif
                </td>
                <td>
                  @if($sale->payment_status == 'paid')
                    <span class="badge badge-success">Paid</span>
                  @elseif($sale->payment_status == 'partial')
                    <span class="badge badge-info">Partial</span>
                  @elseif($sale->payment_status == 'debt')
                    <span class="badge badge-danger">Credit</span>
                  @else
                    <span class="badge badge-warning">Unpaid</span>
                  @endif
                </td>
                <td class="text-nowrap">
                  <a href="{{ route('invoices.show', $sale) }}" class="btn btn-sm btn-primary" title="View / Print Invoice"><i class="fa fa-file-text-o"></i></a>
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
                      data-items='@json($payItems)'><i class="fa fa-money"></i></button>
                  @endif
                  @if($sale->payment_status === 'paid' || (float) $sale->amount_paid > 0)
                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-info" title="View / Print Receipt"><i class="fa fa-print"></i></a>
                  @else
                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-secondary" title="View Receipt"><i class="fa fa-eye"></i></a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        {{ $sales->links() }}
        @else
        <div class="text-center py-5 text-muted">
          <i class="fa fa-file-text-o fa-3x mb-3 d-block"></i>
          <p class="mb-2">No invoices yet.</p>
          @can('process_sales')
            <a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create your first invoice</a>
          @endcan
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('sales.partials.customer-picker-scripts')
@include('sales.partials.payment-modal-scripts')
@endsection
