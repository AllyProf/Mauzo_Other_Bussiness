@extends('layouts.app')

@section('title', 'Sales Shifts')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-clock-o"></i> Sales Shifts</h1>
    <p>Each sales officer opens a shift with a physical stock check before selling</p>
  </div>
  @if(!$openShift)
    @canany(['open_shift', 'process_sales'])
      @if($shiftOpenCheck['allowed'] ?? true)
        <a href="{{ route('shifts.create') }}" class="btn btn-success"><i class="fa fa-play"></i> Open Shift</a>
      @else
        <button type="button" class="btn btn-secondary" disabled title="{{ $shiftOpenCheck['message'] ?? '' }}"><i class="fa fa-ban"></i> Opening Not Allowed Now</button>
      @endif
    @endcanany
  @else
    <div>
      <a href="{{ route('sales.create') }}" class="btn btn-primary mr-1"><i class="fa fa-shopping-cart"></i> Go to POS</a>
      <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="btn btn-warning"><i class="fa fa-balance-scale"></i> End Shift / Handover</a>
    </div>
  @endif
</div>

@if(!$openShift && !($shiftOpenCheck['allowed'] ?? true))
<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert alert-warning mb-0">
      <i class="fa fa-clock-o"></i> {{ $shiftOpenCheck['message'] ?? 'Shift opening is restricted right now.' }}
      <span class="d-block small mt-1">Allowed window: {{ $shiftOpenWindowLabel ?? 'Any time' }}</span>
    </div>
  </div>
</div>
@endif

@if($openShift)
<div class="row mb-3">
  <div class="col-md-12">
    @if(($shiftOverdueStatus['overdue'] ?? false))
      <div class="alert alert-{{ ($shiftOverdueStatus['enforced'] ?? false) ? 'danger' : 'warning' }} mb-2">
        <i class="fa fa-exclamation-triangle"></i> {{ $shiftOverdueStatus['message'] ?? 'This shift has been open too long.' }}
        @if($shiftOverdueStatus['deadline'] ?? null)
          <span class="d-block small mt-1">Close by: {{ $shiftOverdueStatus['deadline']->format('d M Y, h:i A') }}</span>
        @endif
      </div>
    @endif
    <div class="alert alert-success mb-0">
      <strong><i class="fa fa-check-circle"></i> Shift open</strong> since {{ $openShift->opened_at->format('M d, Y h:i A') }}
      — {{ $openShift->sales_count }} sale(s), TZS {{ number_format($openShift->gross_sales, 0) }} gross
      @if($openShift->opening_variance_count > 0)
        <span class="badge badge-warning ml-2">{{ $openShift->opening_variance_count }} opening stock variance(s)</span>
      @endif
      — <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="alert-link font-weight-bold">Submit handover to end shift</a>
    </div>
  </div>
</div>
@endif

@if($pendingHandoverShift ?? null)
<div class="row mb-3">
  <div class="col-md-12">
    <div class="alert alert-warning mb-0">
      <strong><i class="fa fa-balance-scale"></i> Handover pending</strong> for Shift #{{ $pendingHandoverShift->id }}
      (closed {{ $pendingHandoverShift->closed_at->format('M d, Y h:i A') }}).
      <a href="{{ route('day-closing.index', ['shift' => $pendingHandoverShift->id]) }}" class="alert-link font-weight-bold">Go to Daily Reconciliation</a>
    </div>
  </div>
</div>
@endif

@if(($recentShortages ?? collect())->isNotEmpty() && Auth::user()->role === 'owner')
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile border-danger" style="border-left: 4px solid #dc3545;">
      <h3 class="tile-title text-danger"><i class="fa fa-warning"></i> Recent Stock Shortages</h3>
      <div class="tile-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>When</th><th>Shift</th><th>Staff</th><th>Item</th><th class="text-right">Short By</th><th>Reason</th></tr></thead>
          <tbody>
            @foreach($recentShortages as $check)
              <tr>
                <td>{{ $check->recorded_at->format('d M, h:i A') }}</td>
                <td><a href="{{ route('shifts.show', $check->shift) }}">#{{ $check->shift_id }}</a></td>
                <td>{{ $check->shift->user->name ?? '—' }}</td>
                <td>{{ $check->item->name ?? 'Item' }} <span class="badge badge-light">{{ ucfirst($check->check_type) }}</span></td>
                <td class="text-right text-danger font-weight-bold">{{ number_format($check->shortageAmount(), 2) }}</td>
                <td>{{ Str::limit($check->notes, 60) ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="p-3 border-top">
          <a href="{{ route('stock-shortages.index') }}" class="btn btn-sm btn-danger"><i class="fa fa-list"></i> View All Stock Shortages</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

@include('home.partials.my-stock-shortages')

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Shift History</h3>
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Officer</th>
              <th>Opened</th>
              <th>Closed</th>
              <th>Status</th>
              <th>Sales</th>
              <th>Gross</th>
              <th>Collected</th>
              <th>Opening Short</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($shifts as $shift)
              <tr>
                <td><strong>{{ $shift->user->name }}</strong></td>
                <td>{{ $shift->opened_at->format('M d, Y h:i A') }}</td>
                <td>{{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                <td>
                  @if($shift->status === 'open')
                    <span class="badge badge-success">Open</span>
                  @else
                    <span class="badge badge-secondary">Closed</span>
                  @endif
                </td>
                <td>{{ $shift->sales_count }}</td>
                <td>TZS {{ number_format($shift->gross_sales, 0) }}</td>
                <td>TZS {{ number_format($shift->amount_collected, 0) }}</td>
                <td>
                  @if(($shift->opening_shortages_count ?? 0) > 0)
                    <span class="badge badge-danger">{{ $shift->opening_shortages_count }} short</span>
                  @else
                    <span class="text-muted">0</span>
                  @endif
                  @if($shift->opening_variance_count > ($shift->opening_shortages_count ?? 0))
                    <span class="badge badge-warning ml-1">{{ $shift->opening_variance_count - ($shift->opening_shortages_count ?? 0) }} over</span>
                  @endif
                  @if($shift->closing_variance_count !== null)
                    <br><small class="text-muted">Close var: {{ $shift->closing_variance_count }}</small>
                  @endif
                </td>
                <td>
                  <a href="{{ route('shifts.show', $shift) }}" class="btn btn-sm btn-primary" title="View"><i class="fa fa-eye"></i></a>
                  @if($shift->isOpen() && $shift->user_id === Auth::id())
                    <a href="{{ route('day-closing.index', ['shift' => $shift->id]) }}" class="btn btn-sm btn-warning" title="End shift / handover"><i class="fa fa-balance-scale"></i></a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-center text-muted">No shifts recorded yet.</td></tr>
            @endforelse
          </tbody>
        </table>
        {{ $shifts->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
