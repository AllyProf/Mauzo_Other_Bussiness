@extends('layouts.app')

@section('title', 'Sales Shifts')

@section('styles')
<style>
  .shifts-page .shift-officer-cell strong {
    display: block;
    line-height: 1.35;
  }
  .shifts-page .shift-mobile-meta {
    margin-top: 5px;
    line-height: 1.45;
  }
  .shifts-page .shift-actions {
    white-space: nowrap;
  }
  .shifts-page .shift-actions .btn {
    padding: 0.35rem 0.5rem;
  }
  .shifts-page .shifts-title-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  @media (max-width: 991.98px) {
    .shifts-page .app-title {
      flex-wrap: wrap;
      align-items: flex-start !important;
      gap: 10px;
      margin-bottom: 18px;
    }
    .shifts-page .app-title h1 {
      font-size: 1.35rem;
      line-height: 1.35;
    }
    .shifts-page .app-title p {
      display: block !important;
      font-size: 0.88rem;
      font-style: normal;
    }
    .shifts-page .app-title > .btn,
    .shifts-page .app-title > div {
      width: 100%;
    }
    .shifts-page .shifts-title-actions {
      width: 100%;
    }
    .shifts-page .shifts-title-actions .btn {
      flex: 1 1 auto;
      margin-right: 0 !important;
    }
  }

  @media (max-width: 767.98px) {
    .shifts-page .app-title h1 {
      font-size: 1.2rem;
    }
    .shifts-page .app-title p {
      font-size: 0.82rem;
    }
    .shifts-page .tile {
      padding: 14px;
    }
    .shifts-page .alert {
      font-size: 0.88rem;
    }
    .shifts-page .shifts-col-hide-mobile,
    .shifts-page .shortages-col-hide-mobile {
      display: none !important;
    }
    .shifts-page .shifts-table-wrap,
    .shifts-page .recent-shortages-wrap .table-responsive {
      margin: 0 -4px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .shifts-page .shifts-history-table,
    .shifts-page .recent-shortages-wrap table {
      font-size: 13px;
    }
    .shifts-page .shift-actions {
      display: flex;
      gap: 4px;
      justify-content: center;
    }
    .shifts-page .pagination {
      flex-wrap: wrap;
      justify-content: center;
    }
    .shifts-page .recent-shortages-wrap .tile-title {
      font-size: 1rem;
    }
    .shifts-page .stock-shortages-section .tile-title small {
      display: block;
      margin-top: 4px;
    }
  }
</style>
@endsection

@section('content')
<div class="shifts-page">
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
    <div class="shifts-title-actions">
      <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="fa fa-shopping-cart"></i> Go to POS</a>
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
<div class="row mb-3 recent-shortages-wrap">
  <div class="col-md-12">
    <div class="tile border-danger" style="border-left: 4px solid #dc3545;">
      <h3 class="tile-title text-danger"><i class="fa fa-warning"></i> Recent Stock Shortages</h3>
      <div class="tile-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.when') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.shift') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.staff') }}</th>
              <th>{{ __('tables.columns.item') }}</th>
              <th class="text-right shifts-col-hide-mobile">{{ __('tables.columns.short_by') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.reason') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentShortages as $check)
              <tr>
                <td class="shifts-col-hide-mobile">{{ $check->recorded_at->format('d M, h:i A') }}</td>
                <td class="shifts-col-hide-mobile"><a href="{{ route('shifts.show', $check->shift) }}">#{{ $check->shift_id }}</a></td>
                <td class="shifts-col-hide-mobile">{{ $check->shift->user->name ?? '—' }}</td>
                <td>
                  {{ $check->item->name ?? 'Item' }}
                  <span class="badge badge-light">{{ ucfirst($check->check_type) }}</span>
                  <div class="d-md-none shift-mobile-meta">
                    <small class="text-muted d-block">{{ $check->recorded_at->format('d M, h:i A') }}</small>
                    <small class="text-muted d-block">
                      <a href="{{ route('shifts.show', $check->shift) }}">Shift #{{ $check->shift_id }}</a>
                      · {{ $check->shift->user->name ?? '—' }}
                    </small>
                    <small class="text-danger d-block font-weight-bold">Short: {{ number_format($check->shortageAmount(), 2) }}</small>
                    @if($check->notes)
                      <small class="text-muted d-block">{{ Str::limit($check->notes, 60) }}</small>
                    @endif
                  </div>
                </td>
                <td class="text-right text-danger font-weight-bold shifts-col-hide-mobile">{{ number_format($check->shortageAmount(), 2) }}</td>
                <td class="shifts-col-hide-mobile">{{ Str::limit($check->notes, 60) ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
        <div class="p-3 border-top">
          <a href="{{ route('stock-shortages.index') }}" class="btn btn-sm btn-danger btn-block d-md-inline-block"><i class="fa fa-list"></i> View All Stock Shortages</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

<div class="shifts-shortages-wrap">
  @include('home.partials.my-stock-shortages', ['mobileTable' => true])
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Shift History</h3>
      <div class="tile-body">
        <div class="table-responsive shifts-table-wrap">
        <table class="table table-hover table-bordered shifts-history-table">
          <thead>
            <tr>
              <th>{{ __('tables.columns.officer') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.opened') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.closed') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.status') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.sales') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.gross') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.collected') }}</th>
              <th class="shifts-col-hide-mobile">{{ __('tables.columns.opening_short') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($shifts as $shift)
              <tr>
                <td>
                  <div class="shift-officer-cell">
                    <strong>{{ $shift->user->name }}</strong>
                    <div class="d-md-none shift-mobile-meta">
                      <small class="text-muted d-block">Opened: {{ $shift->opened_at->format('M d, Y h:i A') }}</small>
                      <small class="text-muted d-block">Closed: {{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</small>
                      @if($shift->status === 'open')
                        <span class="badge badge-success mt-1">Open</span>
                      @else
                        <span class="badge badge-secondary mt-1">Closed</span>
                      @endif
                      <small class="d-block mt-1">{{ $shift->sales_count }} sale(s)</small>
                      <small class="d-block font-weight-bold">Gross TZS {{ number_format($shift->gross_sales, 0) }}</small>
                      <small class="text-muted d-block">Collected TZS {{ number_format($shift->amount_collected, 0) }}</small>
                      @if(($shift->opening_shortages_count ?? 0) > 0)
                        <span class="badge badge-danger mt-1">{{ $shift->opening_shortages_count }} short</span>
                      @endif
                      @if($shift->opening_variance_count > ($shift->opening_shortages_count ?? 0))
                        <span class="badge badge-warning mt-1">{{ $shift->opening_variance_count - ($shift->opening_shortages_count ?? 0) }} over</span>
                      @endif
                    </div>
                  </div>
                </td>
                <td class="shifts-col-hide-mobile">{{ $shift->opened_at->format('M d, Y h:i A') }}</td>
                <td class="shifts-col-hide-mobile">{{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                <td class="shifts-col-hide-mobile">
                  @if($shift->status === 'open')
                    <span class="badge badge-success">Open</span>
                  @else
                    <span class="badge badge-secondary">Closed</span>
                  @endif
                </td>
                <td class="shifts-col-hide-mobile">{{ $shift->sales_count }}</td>
                <td class="shifts-col-hide-mobile">TZS {{ number_format($shift->gross_sales, 0) }}</td>
                <td class="shifts-col-hide-mobile">TZS {{ number_format($shift->amount_collected, 0) }}</td>
                <td class="shifts-col-hide-mobile">
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
                <td class="shift-actions">
                  <a href="{{ route('shifts.show', $shift) }}" class="btn btn-sm btn-primary" title="{{ __('tables.actions.view') }}"><i class="fa fa-eye"></i></a>
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
        </div>
        {{ $shifts->links() }}
      </div>
    </div>
  </div>
</div>
</div>
@endsection
