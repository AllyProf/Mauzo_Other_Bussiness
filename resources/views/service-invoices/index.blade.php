@extends('layouts.app')

@section('title', 'Service Invoices')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-file-text-o"></i> Service Invoices</h1>
    <p>Invoices for printing, scanning, salon, and other services</p>
  </div>
  @canany(['create_invoices','process_sales'])
  <a href="{{ route('service-invoices.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Service Invoice</a>
  @endcanany
</div>

<div class="row mb-3">
  <div class="col-md-4"><div class="widget-small primary coloured-icon"><div class="info"><h4>{{ $stats['total'] }}</h4><p class="mb-0">Total</p></div></div></div>
  <div class="col-md-4"><div class="widget-small warning coloured-icon"><div class="info"><h4>{{ $stats['unpaid'] }}</h4><p class="mb-0">Unpaid</p></div></div></div>
  <div class="col-md-4"><div class="widget-small info coloured-icon"><div class="info"><h4>{{ money($stats['total_amount']) }}</h4><p class="mb-0">Value</p></div></div></div>
</div>

<div class="tile">
  <div class="table-responsive">
    <table class="table table-hover">
      <thead><tr><th>Date</th><th>Reference</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @forelse($sales as $sale)
        <tr>
          <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td>
          <td>{{ $sale->reference_no }}</td>
          <td>{{ $sale->customer_name ?: $sale->customer?->name ?: '—' }}</td>
          <td>{{ money($sale->total_amount) }}</td>
          <td><span class="badge badge-{{ $sale->payment_status === 'paid' ? 'success' : 'warning' }}">{{ strtoupper($sale->payment_status) }}</span></td>
          <td><a href="{{ route('service-invoices.show', $sale) }}" class="btn btn-sm btn-info">View</a></td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-muted text-center">No service invoices yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $sales->links() }}
</div>
@endsection
