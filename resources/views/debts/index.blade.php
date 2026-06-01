@extends('layouts.app')

@section('title', 'Debt Management - SpareParts POS')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-credit-card"></i> Debt Management</h1>
    <p>{{ ($scopedToSelf ?? false) ? 'Your customer debts only' : 'Track customer balances and collect outstanding payments' }}</p>
  </div>
  <a href="{{ route('sales.index') }}" class="btn btn-secondary"><i class="fa fa-shopping-cart"></i> Sales History</a>
</div>

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Total Outstanding</h4>
        <p><b>{{ money($stats['total_outstanding']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-file-text-o fa-3x"></i>
      <div class="info">
        <h4>Open Accounts</h4>
        <p><b>{{ $stats['open_accounts'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>Overdue</h4>
        <p><b>{{ $stats['overdue_count'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-users fa-3x"></i>
      <div class="info">
        <h4>Customers Owing</h4>
        <p><b>{{ $stats['customers'] }}</b></p>
      </div>
    </div>
  </div>
</div>

@if($customerSummaries->isNotEmpty())
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Top Customer Balances</h3>
      <div class="tile-body">
        <table class="table table-sm table-bordered mb-0">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Phone</th>
              <th>Open Orders</th>
              <th>Total Balance</th>
            </tr>
          </thead>
          <tbody>
            @foreach($customerSummaries as $customer)
              <tr>
                <td><strong>{{ $customer['name'] }}</strong></td>
                <td>{{ $customer['phone'] ?: '—' }}</td>
                <td>{{ $customer['orders'] }}</td>
                <td class="text-danger font-weight-bold">{{ money($customer['balance']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">Outstanding Debts</h3>
        <form method="GET" action="{{ route('debts.index') }}" class="form-inline">
          <input type="text" name="search" class="form-control form-control-sm mr-2 mb-2" placeholder="Search customer, phone, ref..." value="{{ request('search') }}">
          <select name="status" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Types</option>
            <option value="debt" {{ request('status') === 'debt' ? 'selected' : '' }}>Full Debt</option>
            <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial Payment</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
          </select>
          <select name="filter" class="form-control form-control-sm mr-2 mb-2">
            <option value="">All Dates</option>
            <option value="overdue" {{ request('filter') === 'overdue' ? 'selected' : '' }}>Overdue Only</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary mb-2"><i class="fa fa-filter"></i> Filter</button>
          @if(request()->hasAny(['search', 'status', 'filter']))
            <a href="{{ route('debts.index') }}" class="btn btn-sm btn-secondary mb-2 ml-1">Clear</a>
          @endif
        </form>
      </div>
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>Sale Total</th>
              <th>Paid</th>
              <th>Balance Due</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Cashier</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($debts as $sale)
              @php
                $balance = max(0, $sale->total_amount - $sale->amount_paid);
                $isOverdue = $sale->due_date && $sale->due_date < $today;
              @endphp
              <tr class="{{ $isOverdue ? 'table-warning' : '' }}">
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
                <td>{{ $sale->reference_no }}</td>
                <td><strong>{{ $sale->customer_name ?: 'Walk-in / Unnamed' }}</strong></td>
                <td>{{ $sale->customer_phone ?: '—' }}</td>
                <td>{{ money($sale->total_amount) }}</td>
                <td>{{ money($sale->amount_paid) }}</td>
                <td class="text-danger font-weight-bold">{{ money($balance) }}</td>
                <td>
                  @if($sale->due_date)
                    {{ \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') }}
                    @if($isOverdue)
                      <span class="badge badge-danger">Overdue</span>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($sale->payment_status === 'debt')
                    <span class="badge badge-danger">Debt</span>
                  @elseif($sale->payment_status === 'partial')
                    <span class="badge badge-info">Partial</span>
                  @else
                    <span class="badge badge-warning">Pending</span>
                  @endif
                </td>
                <td>{{ $sale->user->name }}</td>
                <td>
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
                    data-sale-id="{{ $sale->id }}"
                    data-ref="{{ e($sale->reference_no) }}"
                    data-total="{{ $sale->total_amount }}"
                    data-paid="{{ $sale->amount_paid }}"
                    data-customer-name="{{ e($sale->customer_name ?? '') }}"
                    data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
                    data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
                    data-items='@json($payItems)'>
                    <i class="fa fa-money"></i> Collect
                  </button>
                  <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Receipt"><i class="fa fa-eye"></i></a>
                </td>
              </tr>
            @endforeach
            @if($debts->isEmpty())
              <tr>
                <td colspan="11" class="text-center">No outstanding debts found.</td>
              </tr>
            @endif
          </tbody>
        </table>
        {{ $debts->links() }}
      </div>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
    @include('sales.partials.payment-modal-scripts')
@endsection
