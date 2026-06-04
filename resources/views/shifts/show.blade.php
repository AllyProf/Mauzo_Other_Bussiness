@extends('layouts.app')

@section('title', 'Shift #' . $shift->id)

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clock-o"></i> Shift #{{ $shift->id }}</h1>
    <p>{{ $shift->user->name }} — {{ $shift->opened_at->format('M d, Y h:i A') }}</p>
  </div>
  <div>
    @if($shift->isOpen() && $shift->user_id === Auth::id())
      <a href="{{ route('sales.create') }}" class="btn btn-primary mr-1"><i class="fa fa-shopping-cart"></i> POS</a>
      <a href="{{ route('day-closing.index', ['shift' => $shift->id]) }}" class="btn btn-warning"><i class="fa fa-balance-scale"></i> End Shift / Handover</a>
    @endif
    <a href="{{ route('shifts.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> All Shifts</a>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i><div class="info"><h4>Sales</h4><p><b>{{ $shift->sales_count }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small info coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i><div class="info"><h4>Gross</h4><p><b>TZS {{ number_format($shift->gross_sales, 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small success coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>Collected</h4><p><b>TZS {{ number_format($shift->amount_collected, 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small {{ $shift->status === 'open' ? 'warning' : 'secondary' }} coloured-icon"><i class="icon fa fa-{{ $shift->status === 'open' ? 'play' : 'stop' }} fa-3x"></i><div class="info"><h4>Status</h4><p><b>{{ ucfirst($shift->status) }}</b></p></div></div></div>
</div>

<div class="row">
  @if($shift->openingShortages->isNotEmpty())
  <div class="col-md-12 mb-3" id="shortages">
    <div class="tile border-danger" style="border-left: 4px solid #dc3545;">
      <h3 class="tile-title text-danger"><i class="fa fa-warning"></i> Items Recorded Short (Opening)</h3>
      <div class="tile-body table-responsive p-0">
        <table class="table table-sm table-bordered mb-0">
          <thead class="thead-light">
            <tr>
              <th>Item</th>
              <th class="text-right">System Stock</th>
              <th class="text-right">Physical Count</th>
              <th class="text-right">Short By</th>
              <th>Staff Reason</th>
              <th>Owner Decision</th>
              <th>Recorded By</th>
            </tr>
          </thead>
          <tbody>
            @foreach($shift->openingShortages as $check)
              <tr class="table-danger">
                <td><strong>{{ $check->item->name ?? 'Item' }}</strong></td>
                <td class="text-right">{{ number_format($check->system_stock, 2) }}</td>
                <td class="text-right">{{ number_format($check->counted_stock, 2) }}</td>
                <td class="text-right font-weight-bold text-danger">{{ number_format($check->shortageAmount(), 2) }}</td>
                <td>{{ $check->notes ?: '—' }}</td>
                <td>
                  @if($check->isVerified())
                    @if($check->isWillBePaid())
                      <span class="badge badge-primary">Will be paid</span>
                    @elseif($check->isWaived())
                      <span class="badge badge-success">Waived</span>
                    @else
                      <span class="badge badge-secondary">Reviewed</span>
                    @endif
                    @if($check->owner_notes)
                      <br><small class="text-muted">{{ $check->owner_notes }}</small>
                    @endif
                  @else
                    <span class="badge badge-warning">Pending</span>
                  @endif
                </td>
                <td>{{ $check->recorder->name ?? $shift->user->name }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @endif

  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Opening Stock Check</h3>
      <div class="tile-body table-responsive">
        <table class="table table-sm table-bordered">
          <thead><tr><th>Item</th><th class="text-right">System</th><th class="text-right">Counted</th><th class="text-right">Variance</th><th>Notes</th></tr></thead>
          <tbody>
            @forelse($shift->openingChecks as $check)
              <tr class="{{ $check->isShort() ? 'table-danger' : (abs($check->variance) > 0.001 ? 'table-warning' : '') }}">
                <td>{{ $check->item->name ?? 'Item' }}</td>
                <td class="text-right">{{ number_format($check->system_stock, 2) }}</td>
                <td class="text-right">{{ number_format($check->counted_stock, 2) }}</td>
                <td class="text-right {{ $check->isShort() ? 'text-danger font-weight-bold' : (abs($check->variance) > 0.001 ? 'text-warning font-weight-bold' : 'text-success') }}">
                  {{ number_format($check->variance, 2) }}
                  @if($check->isShort())
                    <br><small>(short {{ number_format($check->shortageAmount(), 2) }})</small>
                  @endif
                </td>
                <td class="small">{{ $check->notes ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-muted text-center">No opening check recorded.</td></tr>
            @endforelse
          </tbody>
        </table>
        @if($shift->opening_notes)
          <p class="small text-muted mb-0"><strong>Notes:</strong> {{ $shift->opening_notes }}</p>
        @endif
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Closing Stock Check</h3>
      <div class="tile-body table-responsive">
        <table class="table table-sm table-bordered">
          <thead><tr><th>Item</th><th class="text-right">System</th><th class="text-right">Counted</th><th class="text-right">Variance</th></tr></thead>
          <tbody>
            @forelse($shift->closingChecks as $check)
              <tr class="{{ abs($check->variance) > 0.001 ? 'table-warning' : '' }}">
                <td>{{ $check->item->name ?? 'Item' }}</td>
                <td class="text-right">{{ number_format($check->system_stock, 2) }}</td>
                <td class="text-right">{{ number_format($check->counted_stock, 2) }}</td>
                <td class="text-right {{ abs($check->variance) > 0.001 ? 'text-danger font-weight-bold' : 'text-success' }}">{{ number_format($check->variance, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted text-center">{{ $shift->isOpen() ? 'Complete when closing shift.' : 'No closing check recorded.' }}</td></tr>
            @endforelse
          </tbody>
        </table>
        @if($shift->closing_notes)
          <p class="small text-muted mb-0"><strong>Notes:</strong> {{ $shift->closing_notes }}</p>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Sales During Shift</h3>
  <div class="tile-body table-responsive">
    <table class="table table-bordered table-sm">
      <thead><tr><th>Ref</th><th>Time</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead>
      <tbody>
        @forelse($shift->sales as $sale)
          <tr>
            <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->reference_no }}</a></td>
            <td>{{ $sale->created_at->format('h:i A') }}</td>
            <td>TZS {{ number_format($sale->total_amount, 2) }}</td>
            <td>TZS {{ number_format($sale->amount_paid, 2) }}</td>
            <td><span class="badge badge-{{ $sale->payment_status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($sale->payment_status) }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted">No sales in this shift yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
