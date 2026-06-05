@extends('layouts.app')

@section('title', 'Stock Shortages')

@section('styles')
<style>
  .shortage-row { cursor: pointer; transition: background 0.15s ease; }
  .shortage-row:hover { background-color: rgba(148, 0, 0, 0.04) !important; }
  .shortage-row.is-expanded { background-color: #fff9f1 !important; }
  .shortage-row .expand-icon { width: 28px; color: #940000; transition: transform 0.2s ease; }
  .shortage-row.is-expanded .expand-icon { transform: rotate(90deg); }
  .shortage-detail td { padding: 0 !important; border-top: none !important; background: #fafafa; }
  .shortage-impact-panel {
    border-left: 4px solid #940000;
    padding: 14px 16px;
    margin: 0;
  }
  .impact-metric {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px 12px;
    height: 100%;
  }
  .impact-metric .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #6c757d;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
  }
  .impact-metric .value { font-size: 1rem; font-weight: 700; color: #212529; }
  .impact-metric.highlight .value { color: #940000; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-warning"></i> Stock Shortages</h1>
    <p>Items recorded short during shift stock checks — review each entry and mark as <strong>will be paid</strong> or <strong>waived</strong>.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('shifts.index') }}">Shifts</a></li>
    <li class="breadcrumb-item active">Stock Shortages</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
  <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@include('partials.branch-business-filters', ['filterHint' => 'Select a business tab to filter shortage records by department.'])

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Opening Shortages</h4>
        <p><b>{{ number_format($stats['opening_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Closing Shortages</h4>
        <p><b>{{ number_format($stats['closing_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>On Open Shifts</h4>
        <p><b>{{ number_format($stats['open_shift_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-hourglass-half fa-3x"></i>
      <div class="info">
        <h4>Awaiting Review</h4>
        <p><b>{{ number_format($stats['pending_verification']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Shortage Log</h3>
  <div class="tile-body">
    <form method="GET" action="{{ route('stock-shortages.index') }}" class="row mb-3">
      @if($activeBusinessType ?? false)
        <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
      @endif
      <div class="col-md-4 mb-2 mb-md-0">
        <label class="small font-weight-bold">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Item, staff, or notes...">
      </div>
      <div class="col-md-3 mb-2 mb-md-0">
        <label class="small font-weight-bold">Review</label>
        <select name="status" class="form-control form-control-sm">
          <option value="">All</option>
          <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Awaiting review</option>
          <option value="will_be_paid" {{ request('status') === 'will_be_paid' ? 'selected' : '' }}>Will be paid</option>
          <option value="waived" {{ request('status') === 'waived' ? 'selected' : '' }}>Waived</option>
          <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>All reviewed</option>
        </select>
      </div>
      <div class="col-md-3 mb-2 mb-md-0">
        <label class="small font-weight-bold">Staff</label>
        <select name="staff_id" class="form-control form-control-sm">
          <option value="">All staff</option>
          @foreach($staffMembers as $member)
            <option value="{{ $member->id }}" {{ (string) request('staff_id') === (string) $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> Search</button>
      </div>
    </form>

    <p class="text-muted small mb-2"><i class="fa fa-hand-pointer-o"></i> Click any row to expand financial impact (revenue, cost, profit).</p>

    <div class="table-responsive">
      <table class="table table-hover table-bordered table-sm" id="shortagesTable">
        <thead class="thead-dark">
          <tr>
            <th style="width:32px;"></th>
            <th>Date / Time</th>
            <th>{{ __('tables.columns.shift') }}</th>
            <th>{{ __('tables.columns.officer') }}</th>
            <th>{{ __('tables.columns.check') }}</th>
            <th>{{ __('tables.columns.item') }}</th>
            <th class="text-right">System</th>
            <th class="text-right">Counted</th>
            <th class="text-right">{{ __('tables.columns.short_by') }}</th>
            <th>{{ __('tables.columns.reason_notes') }}</th>
            <th>{{ __('tables.columns.status') }}</th>
            <th class="text-center" style="min-width:150px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($shortages as $check)
            @php $impact = $check->financial_impact ?? []; @endphp
            <tr class="shortage-row {{ $check->isVerified() ? 'table-light' : 'table-danger' }}"
                data-target="#shortage-detail-{{ $check->id }}"
                aria-expanded="false">
              <td class="text-center align-middle">
                <i class="fa fa-chevron-right expand-icon"></i>
              </td>
              <td nowrap>{{ $check->recorded_at->format('d M, Y h:i A') }}</td>
              <td>
                <a href="{{ route('shifts.show', $check->shift) }}" class="shortage-no-toggle">#{{ $check->shift_id }}</a>
                @if($check->shift->status === 'open')
                  <span class="badge badge-success">Open</span>
                @endif
              </td>
              <td>{{ $check->shift->user->name ?? '—' }}</td>
              <td><span class="badge badge-{{ $check->check_type === 'opening' ? 'primary' : 'secondary' }}">{{ ucfirst($check->check_type) }}</span></td>
              <td>
                <strong>{{ $check->item->name ?? 'Item' }}</strong>
                @if($check->item?->category)
                  <br><small class="text-muted">{{ $check->item->category->name }}</small>
                @endif
              </td>
              <td class="text-right">{{ number_format($check->system_stock, 2) }}</td>
              <td class="text-right">{{ number_format($check->counted_stock, 2) }}</td>
              <td class="text-right font-weight-bold text-danger">{{ number_format($check->shortageAmount(), 2) }}</td>
              <td style="max-width:200px;">
                {{ $check->notes ?: '—' }}
                @if($check->owner_notes)
                  <br><small class="text-success"><strong>Owner:</strong> {{ $check->owner_notes }}</small>
                @endif
              </td>
              <td>
                @if($check->isVerified())
                  @if($check->isWillBePaid())
                    <span class="badge badge-primary"><i class="fa fa-money"></i> Will be paid</span>
                  @elseif($check->isWaived())
                    <span class="badge badge-success"><i class="fa fa-hand-paper-o"></i> Waived</span>
                  @else
                    <span class="badge badge-success"><i class="fa fa-check"></i> Reviewed</span>
                  @endif
                  <br><small class="text-muted">{{ $check->verified_at->format('d M, h:i A') }}</small>
                @else
                  <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
                @endif
              </td>
              <td class="text-center text-nowrap shortage-no-toggle">
                @if(! $check->isVerified())
                  <form action="{{ route('stock-shortages.verify', $check) }}" method="POST" class="shortage-decision-form d-inline">
                    @csrf
                    <input type="hidden" name="owner_decision" value="">
                    <input type="hidden" name="owner_notes" value="">
                    <button type="button"
                            class="btn btn-xs btn-primary shortage-decision-btn mb-1"
                            data-decision="will_be_paid"
                            title="Staff must pay for missing stock">
                      <i class="fa fa-money"></i> Will Pay
                    </button>
                    <button type="button"
                            class="btn btn-xs btn-success shortage-decision-btn"
                            data-decision="waived"
                            title="Forgive this shortage">
                      <i class="fa fa-hand-paper-o"></i> Waive
                    </button>
                  </form>
                @else
                  <form action="{{ route('stock-shortages.revert', $check) }}" method="POST" class="shortage-revert-form d-inline shortage-no-toggle">
                    @csrf
                    <button type="button"
                            class="btn btn-xs btn-outline-secondary shortage-revert-btn"
                            title="Undo decision and review again">
                      <i class="fa fa-undo"></i> Undo
                    </button>
                  </form>
                @endif
              </td>
            </tr>
            <tr class="collapse shortage-detail" id="shortage-detail-{{ $check->id }}">
              <td colspan="12">
                <div class="shortage-impact-panel">
                  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <strong class="text-dark"><i class="fa fa-calculator text-primary"></i> Financial impact — {{ $check->item->name ?? 'Item' }}</strong>
                    <small class="text-muted">Based on selling price &amp; cost per piece × quantity short</small>
                  </div>
                  <div class="row">
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                      <div class="impact-metric">
                        <div class="label">Qty Short</div>
                        <div class="value">{{ number_format($impact['shortage_qty'] ?? $check->shortageAmount(), 2) }} pcs</div>
                      </div>
                    </div>
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                      <div class="impact-metric">
                        <div class="label">Unit Cost</div>
                        <div class="value">{{ ($impact['unit_cost'] ?? 0) > 0 ? money($impact['unit_cost']) : '—' }}</div>
                      </div>
                    </div>
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                      <div class="impact-metric">
                        <div class="label">Unit Sell</div>
                        <div class="value">{{ ($impact['unit_sell'] ?? 0) > 0 ? money($impact['unit_sell']) : '—' }}</div>
                      </div>
                    </div>
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                      <div class="impact-metric highlight">
                        <div class="label">Lost Revenue</div>
                        <div class="value">{{ money($impact['revenue_value'] ?? 0) }}</div>
                      </div>
                    </div>
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                      <div class="impact-metric">
                        <div class="label">Lost Cost</div>
                        <div class="value">{{ money($impact['cost_value'] ?? 0) }}</div>
                      </div>
                    </div>
                    <div class="col-md-2 col-6">
                      <div class="impact-metric highlight">
                        <div class="label">Lost Profit</div>
                        <div class="value">{{ money($impact['profit_value'] ?? 0) }}</div>
                      </div>
                    </div>
                  </div>
                  @if($check->isVerified())
                    <div class="mt-3 pt-2 border-top small">
                      <strong>Owner decision:</strong>
                      @if($check->isWillBePaid())
                        <span class="badge badge-primary">Will be paid</span>
                        <span class="text-muted ml-1">Recorded for collection from staff — collect {{ money($impact['cost_value'] ?? 0) }} (cost) or {{ money($impact['revenue_value'] ?? 0) }} (sales value) outside the system or at handover.</span>
                      @elseif($check->isWaived())
                        <span class="badge badge-success">Waived</span>
                        <span class="text-muted ml-1">No payment required from staff.</span>
                      @endif
                      @if($check->owner_notes)
                        <div class="text-muted mt-1"><strong>Note:</strong> {{ $check->owner_notes }}</div>
                      @endif
                    </div>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="12" class="text-center py-4 text-muted">No stock shortages recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
      {{ $shortages->links() }}
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  $('.shortage-row').on('click', function(e) {
    if ($(e.target).closest('.shortage-no-toggle, .shortage-decision-btn, .shortage-revert-btn, a').length) {
      return;
    }

    const $row = $(this);
    const target = $row.data('target');
    const $detail = $(target);
    const isOpen = $detail.hasClass('show');

    $('.shortage-detail.show').collapse('hide');
    $('.shortage-row.is-expanded').removeClass('is-expanded').attr('aria-expanded', 'false');

    if (!isOpen) {
      $detail.collapse('show');
      $row.addClass('is-expanded').attr('aria-expanded', 'true');
    }
  });

  const decisionCopy = {
    will_be_paid: {
      title: 'Mark as will be paid?',
      text: 'Staff must pay for the missing stock. You can add a note for your records.',
      confirmColor: '#940000',
      confirmText: '<i class="fa fa-money"></i> Will be paid'
    },
    waived: {
      title: 'Waive this shortage?',
      text: 'Staff will not be required to pay for the missing stock.',
      confirmColor: '#28a745',
      confirmText: '<i class="fa fa-hand-paper-o"></i> Waive'
    }
  };

  $('.shortage-decision-btn').on('click', function(e) {
    e.stopPropagation();
    const form = $(this).closest('form');
    const btn = $(this);
    const decision = btn.data('decision');
    const copy = decisionCopy[decision] || decisionCopy.waived;

    Swal.fire({
      title: copy.title,
      text: copy.text,
      input: 'textarea',
      inputLabel: 'Owner note (optional)',
      inputPlaceholder: decision === 'will_be_paid'
        ? 'e.g. Deduct from next handover / salary'
        : 'e.g. Accepted — minor count error',
      showCancelButton: true,
      confirmButtonColor: copy.confirmColor,
      confirmButtonText: copy.confirmText,
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        form.find('input[name="owner_decision"]').val(decision);
        form.find('input[name="owner_notes"]').val(result.value || '');
        form.find('.shortage-decision-btn').prop('disabled', true);
        btn.html('<i class="fa fa-spinner fa-spin"></i>');
        form.submit();
      }
    });
  });

  $('.shortage-revert-btn').on('click', function(e) {
    e.stopPropagation();
    const form = $(this).closest('form');
    const btn = $(this);

    Swal.fire({
      title: 'Undo this decision?',
      text: 'The shortage will return to Pending. You can choose Will Pay or Waive again.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#6c757d',
      confirmButtonText: '<i class="fa fa-undo"></i> Undo',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        form.submit();
      }
    });
  });
});
</script>
@endsection
