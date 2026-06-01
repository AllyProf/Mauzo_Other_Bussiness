@extends('layouts.app')

@section('title', $customer->name . ' — Customer')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-user"></i> {{ $customer->name }}</h1>
    <p>Customer profile and purchase history</p>
  </div>
  <div>
    @can('manage_customers')
    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-info"><i class="fa fa-edit"></i> Edit</a>
    @endcan
    <a href="{{ route('customers.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small info coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i>
      <div class="info"><h4>Total Sales</h4><p><b>{{ $stats['total_sales'] }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-money fa-3x"></i>
      <div class="info"><h4>Total Paid</h4><p><b>{{ money($stats['total_spent']) }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small {{ $stats['outstanding'] > 0 ? 'danger' : 'primary' }} coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info"><h4>Outstanding</h4><p><b>{{ money($stats['outstanding']) }}</b></p></div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title">Contact Details</h3>
      <div class="tile-body">
        <table class="table table-sm">
          <tr><th>Phone</th><td>{{ $customer->phone }}</td></tr>
          <tr><th>Email</th><td>{{ $customer->email ?: '—' }}</td></tr>
          <tr><th>Region</th><td>{{ $customer->region ?: '—' }}</td></tr>
          <tr><th>Address</th><td>{{ $customer->address ?: '—' }}</td></tr>
          <tr>
            <th>Status</th>
            <td>
              @if($customer->is_active)
                <span class="badge badge-success">Active</span>
              @else
                <span class="badge badge-secondary">Inactive</span>
              @endif
            </td>
          </tr>
        </table>
        @if($customer->notes)
        <hr>
        <strong>Notes</strong>
        <p class="text-muted mb-0">{{ $customer->notes }}</p>
        @endif
        @if($stats['outstanding'] > 0)
        <hr>
        <a href="{{ route('debts.index', ['search' => $customer->phone]) }}" class="btn btn-warning btn-sm btn-block">
          <i class="fa fa-credit-card"></i> View in Debt Management
        </a>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="tile">
      <h3 class="tile-title">Sales History</h3>
      <div class="tile-body">
        <table class="table table-hover table-bordered table-sm">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Date</th>
              <th>Staff</th>
              <th class="text-right">Total</th>
              <th class="text-right">Paid</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sales as $sale)
            <tr class="{{ $sale->payment_status === 'cancelled' ? 'table-secondary' : '' }}">
              <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference_no }}</a></td>
              <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</td>
              <td>{{ $sale->user->name ?? '—' }}</td>
              <td class="text-right">{{ money($sale->total_amount) }}</td>
              <td class="text-right">{{ money($sale->amount_paid) }}</td>
              <td>
                @php
                  $badge = match($sale->payment_status) {
                    'paid' => 'success',
                    'partial', 'debt', 'pending' => 'warning',
                    'cancelled' => 'secondary',
                    default => 'info',
                  };
                @endphp
                <span class="badge badge-{{ $badge }}">{{ ucfirst($sale->payment_status) }}</span>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No linked sales yet. Sales will appear here when this customer is selected at POS.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
