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
            <th>Date</th>
            <th>Reference</th>
            <th>Services sold</th>
            <th>Cashier</th>
            <th>Total</th>
            <th>Status</th>
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
              @if($sale->payment_status === 'paid')<span class="badge badge-success">Paid</span>
              @elseif($sale->payment_status === 'cancelled')<span class="badge badge-secondary">Cancelled</span>
              @else<span class="badge badge-warning">{{ ucfirst($sale->payment_status) }}</span>@endif
            </td>
            <td>
              <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary">View</a>
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No service sales yet. Use <a href="{{ route('service-pos.create') }}">Service POS</a> to record a sale.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $sales->links() }}
  </div>
</div>
@endsection
