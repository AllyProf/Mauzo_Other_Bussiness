@forelse($ledgers as $index => $ledger)
  @php
    $closingRouteId = $ledger['detail_closing_id'] ?? $ledger['id'];
    $isPlaceholder = $ledger['is_placeholder'] ?? false;
    $canExpand = $isPlaceholder ? ($ledger['has_open_day_activity'] ?? false) : true;
  @endphp
  <div class="or-mobile-card main-row {{ ($ledger['is_manager_received'] ?? false) ? 'manager-received' : '' }}"
       data-ledger-date="{{ $ledger['ledger_date'] }}"
       @if($canExpand) data-toggle="collapse" data-target="#mobile-details-{{ $ledger['id'] }}" aria-expanded="false" @endif>
    <div class="or-mobile-head">
      <div>
        <div class="or-mobile-date">{{ \Carbon\Carbon::parse($ledger['ledger_date'])->format('d M, Y') }}</div>
        <div class="or-mobile-meta">
          @if($multiBusiness ?? false)
            <strong>{{ $ledger['business_type_label'] ?? '—' }}</strong> ·
          @endif
          {{ $ledger['handover_label'] ?? $ledger['submitted_by'] ?? (($ledger['has_open_shift'] ?? false) ? __('owner_reports.open_day') : __('owner_reports.awaiting_shift')) }}
        </div>
      </div>
      <span class="status-badge" style="border: 1px solid {{ $ledger['status_color'] }}; color: {{ $ledger['status_color'] }};">{{ __report_status($ledger['business_status']) }}</span>
    </div>
    <div class="or-mobile-grid">
      <div class="or-mobile-stat"><span>{{ __('owner_reports.columns.total') }}</span><strong>TZS {{ number_format($ledger['sub_total'] ?? 0, 0) }}</strong></div>
      <div class="or-mobile-stat"><span>{{ __('owner_reports.columns.assets') }}</span><strong>TZS {{ number_format($ledger['total_assets'], 0) }}</strong></div>
      <div class="or-mobile-stat"><span>{{ __('owner_reports.columns.daily_profit') }}</span><strong class="text-success">TZS {{ number_format($ledger['daily_net_profit'] ?? $ledger['net_available_profit'] ?? 0, 0) }}</strong></div>
      <div class="or-mobile-stat"><span>{{ __('owner_reports.columns.circulation_rollover') }}</span><strong class="text-primary">TZS {{ number_format($ledger['money_in_circulation'] ?? 0, 0) }}</strong></div>
      <div class="or-mobile-stat"><span>{{ __('owner_reports.columns.profit_rollover') }}</span><strong class="text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</strong></div>
    </div>
    @if($isPlaceholder)
    <div class="or-mobile-actions d-print-none" onclick="event.stopPropagation();">
      <a href="{{ route('petty-cash.index', ['date' => $ledger['ledger_date']]) }}" class="btn btn-outline-primary btn-sm" title="{{ __('owner_reports.petty_cash') }}"><i class="fa fa-money"></i></a>
      <a href="{{ route('day-closing.index') }}" class="btn btn-warning btn-sm" title="{{ __('owner_reports.awaiting_handover') }}"><i class="fa fa-clock-o"></i></a>
      @if($canExpand)
        <span class="or-mobile-expand-hint"><i class="fa fa-chevron-down"></i></span>
      @endif
    </div>
    @else
    <div class="or-mobile-actions d-print-none" onclick="event.stopPropagation();">
      <a href="{{ route('day-closing.show', $closingRouteId) }}" class="btn btn-primary btn-sm"><i class="fa fa-eye"></i></a>
      <a href="{{ route('day-closing.show', $closingRouteId) }}" target="_blank" class="btn btn-dark btn-sm"><i class="fa fa-print"></i></a>
      @if($canExpand)
        <span class="or-mobile-expand-hint"><i class="fa fa-chevron-down"></i></span>
      @endif
    </div>
    @endif
  </div>
  @if($canExpand)
  <div id="mobile-details-{{ $ledger['id'] }}" class="collapse or-mobile-detail">
    @include('owner-reports.partials.ledger-detail-body', [
      'ledger' => $ledger,
      'closingRouteId' => $closingRouteId,
      'multiBusiness' => $multiBusiness ?? false,
    ])
  </div>
  @endif
@empty
  <p class="text-center text-muted py-4 mb-0">
    @if(!empty($canSwitchBranch) && empty($viewingAllBranches))
      {{ __('owner_reports.empty.reports_branch', ['branch' => $activeBranchLabel ?? __('common.branch')]) }}
    @else
      {{ __('owner_reports.empty.reports') }}
    @endif
  </p>
@endforelse
