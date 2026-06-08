@php
  $closing = $ownerDirectCloseCard['dayClosing'] ?? $ownerDirectClosing ?? null;
  $financeData = $ownerDirectCloseCard['financeData'] ?? [];
  $handoverSummary = $ownerDirectCloseCard['handoverSummary'] ?? [];
@endphp

@if($closing)
<div class="alert alert-success border mb-0" id="owner-day-close">
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
    <div>
      <h5 class="alert-heading mb-1"><i class="fa fa-check-circle"></i> Your day is closed &amp; posted</h5>
      <p class="mb-0 small">
        Closed {{ $closing->verified_at?->format('M d, Y h:i A') ?? $closing->submitted_at?->format('M d, Y h:i A') ?? '—' }}
        · {{ $closing->sales_count }} sale(s)
        · {{ money($closing->gross_sales) }} gross
        · {{ money($closing->amount_collected) }} collected on orders
      </p>
    </div>
    <div class="mt-2 mt-md-0">
      <a href="{{ route('owner-reports.index', ['start_date' => $date, 'end_date' => $date, 'highlight_date' => $date]) }}" class="btn btn-sm btn-outline-success mr-1 mb-1">Master Sheet</a>
      @if($closing->hasMoneyShort())
        <a href="{{ route('money-shorts.index') }}" class="btn btn-sm btn-outline-danger mb-1">Money Shorts</a>
      @endif
    </div>
  </div>

  <div class="row text-center bg-white rounded border py-3 mb-3">
    <div class="col-md-4 mb-2 mb-md-0">
      <small class="text-uppercase font-weight-bold text-muted">Expected Handover</small>
      <div class="font-weight-bold">{{ money($closing->expectedHandoverAmount()) }}</div>
    </div>
    <div class="col-md-4 mb-2 mb-md-0">
      <small class="text-uppercase font-weight-bold text-muted">Actual Received</small>
      <div class="font-weight-bold text-success">{{ money($closing->actual_received ?? $closing->expectedHandoverAmount()) }}</div>
    </div>
    <div class="col-md-4">
      <small class="text-uppercase font-weight-bold text-muted">Money Short</small>
      <div class="font-weight-bold {{ $closing->hasMoneyShort() ? 'text-danger' : 'text-muted' }}">
        {{ $closing->hasMoneyShort() ? money($closing->money_short) : '—' }}
      </div>
    </div>
  </div>

  @if($closing->hasMoneyShort() && $closing->shortage_note)
    <div class="bg-white rounded border p-3 mb-3">
      <small class="text-uppercase font-weight-bold text-muted">Shortage note</small>
      <div class="mb-0">{{ $closing->shortage_note }}</div>
    </div>
  @endif

  @if(!empty($financeData))
    <div class="row text-center bg-white rounded border py-3 mb-0">
      <div class="col-md-4 mb-2 mb-md-0">
        <small class="text-uppercase font-weight-bold text-muted">Credit / Debt</small>
        <div class="font-weight-bold {{ ($financeData['outstanding_debt'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
          {{ money($financeData['outstanding_debt'] ?? 0) }}
        </div>
      </div>
      <div class="col-md-4 mb-2 mb-md-0">
        <small class="text-uppercase font-weight-bold text-muted">Profit</small>
        <div class="font-weight-bold text-success">{{ money($financeData['net_profit'] ?? 0) }}</div>
      </div>
      <div class="col-md-4">
        <small class="text-uppercase font-weight-bold text-muted">Money in Circulation</small>
        <div class="font-weight-bold text-primary">{{ money($financeData['closing_circulation'] ?? 0) }}</div>
      </div>
    </div>
  @endif

  @if($closing->report_notes)
    <div class="bg-white rounded border p-3 mt-3 mb-0">
      <small class="text-uppercase font-weight-bold text-muted">Your note</small>
      <div class="mb-0">{!! nl2br(e($closing->report_notes)) !!}</div>
    </div>
  @endif
</div>
@endif
