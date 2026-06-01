@extends('layouts.app')

@section('title', 'Sales History - SpareParts POS')

@section('styles')
<style>
  #salesTable td.sold-items-cell {
    max-width: 220px;
    white-space: normal;
    font-size: 0.9rem;
    line-height: 1.35;
  }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-shopping-cart"></i> Sales History</h1>
    <p>
      @if(($shiftContext ?? '') === 'current')
        Sales for your current shift #{{ $openShift->id }} only
      @elseif(($shiftContext ?? '') === 'none')
        Open a shift to record new sales — previous shift sales are in Shift History
      @elseif($scopedToSelf ?? false)
        Your sales activity only
      @else
        View all completed sales
      @endif
    </p>
  </div>
  @if(($requiresOpenShift ?? false) && !($openShift ?? null))
    <a href="{{ route('shifts.create') }}" class="btn btn-warning"><i class="fa fa-clock-o"></i> Open Shift First</a>
  @else
    <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Sale (POS)</a>
  @endif
</div>

@if(($requiresOpenShift ?? false) && !($openShift ?? null))
<div class="alert alert-warning">
  <i class="fa fa-exclamation-triangle"></i> You must <strong>open a shift</strong> with a physical stock check before using the POS.
  <a href="{{ route('shifts.create') }}" class="alert-link">Open shift now</a>
  · <a href="{{ route('shifts.index') }}" class="alert-link">View past shifts</a>
</div>
@elseif($openShift ?? false)
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-clock-o"></i> Shift #{{ $openShift->id }} is open.
  <a href="{{ route('shifts.show', $openShift) }}" class="alert-link">View</a>
</div>
@endif

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info">
        <h4>Total Sales</h4>
        <p><b>{{ number_format($stats['total_sales']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <h4>Gross Sales</h4>
        <p><b>TZS {{ number_format($stats['gross_sales'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Collected</h4>
        <p><b>TZS {{ number_format($stats['collected'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info">
        <h4>Outstanding</h4>
        <p><b>TZS {{ number_format($stats['outstanding'], 0) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="salesTable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Reference No</th>
              <th>Items Sold</th>
              <th>Cashier</th>
              <th>Total Amount</th>
              <th>Status</th>
              <th>Payment Details</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sales as $sale)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
                    <td>{{ $sale->reference_no }}</td>
                    <td class="sold-items-cell">
                      @php $soldSummary = $sale->soldItemsSummary(); @endphp
                      @if($soldSummary)
                        <span class="text-dark" title="{{ $soldSummary }}">{{ $soldSummary }}</span>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>{{ $sale->user->name }}</td>
                    <td class="text-success font-weight-bold">{{ money($sale->total_amount) }}</td>
                    <td>
                        @if($sale->payment_status == 'paid')
                            <span class="badge badge-success">Paid</span>
                        @elseif($sale->payment_status == 'partial')
                            <span class="badge badge-info">Partial</span>
                        @elseif($sale->payment_status == 'debt')
                            <span class="badge badge-danger">Debt</span>
                        @elseif($sale->payment_status == 'cancelled')
                            <span class="badge badge-secondary">Cancelled</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
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
                                        'name' => $si->item->name ?? 'Item',
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
                      @if(($shiftContext ?? '') === 'none')
                        No active shift. Open a shift to start selling — closed shift sales are listed under <a href="{{ route('shifts.index') }}">Sales Shifts</a>.
                      @else
                        No sales records found.
                      @endif
                    </td>
                </tr>
            @endif
          </tbody>
        </table>
        {{ $sales->links() }}
      </div>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script type="text/javascript" src="{{ asset('admin/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('admin/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">
        $('#salesTable').DataTable({
            "order": [[ 0, "desc" ]]
        });
    </script>
    @include('sales.partials.customer-picker-scripts')
    @include('sales.partials.payment-modal-scripts')
@endsection
