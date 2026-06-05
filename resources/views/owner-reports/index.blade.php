@extends('layouts.app')

@section('title', __('owner_reports.title'))

@section('styles')
<style>
  .excel-table { font-size: 0.9rem; width: 100% !important; margin-bottom: 0 !important; }
  .excel-table th { background: #212529 !important; color: white !important; font-size: 0.75rem; vertical-align: middle !important; padding: 12px 8px !important; }
  .excel-table td { vertical-align: middle !important; padding: 10px 12px !important; }
  .excel-table tr.main-row { cursor: pointer; transition: background 0.2s; }
  .excel-table tr.main-row:hover { background-color: #f1f3f5 !important; }
  .excel-table tr.main-row[aria-expanded="true"] { background-color: #e7f3ff !important; border-bottom: none !important; }
  .money-column { text-align: right; font-family: 'Courier New', Courier, monospace; }
  .status-badge { font-size: 0.65rem; padding: 2px 5px; border-radius: 3px; font-weight: bold; text-transform: uppercase; }
  .detail-row { background-color: #fcfcfc !important; }
  .detail-container { padding: 20px 40px; border-left: 5px solid #940000; box-shadow: inset 0 3px 6px rgba(0,0,0,0.08); background: #fdfdfd; }
  .nested-table { font-size: 0.85rem; background: white; border: 1px solid #dee2e6; }
  .nested-table th { background: #6c757d !important; color: white !important; text-transform: uppercase; font-size: 0.7rem; border: none !important; }
  .manager-received { background-color: #e8f5e9 !important; }
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
  .business-type-row td:first-child + td + td { font-weight: 600; color: #940000; }
  @media print {
    .d-print-none { display: none !important; }
    .excel-table { font-size: 10pt; width: 100% !important; }
    .excel-table th { background: #eee !important; color: #000 !important; border: 1px solid #000 !important; }
    .excel-table td { border: 1px solid #000 !important; }
    .app-content { margin: 0 !important; padding: 10px !important; }
    @page { size: landscape; margin: 0.5cm; }
  }
</style>
@endsection

@section('content')
<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-list-alt"></i> {{ __('owner_reports.archive_title') }}</h1>
    <p>{{ __('owner_reports.subtitle') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">{{ __('menu.dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('owner_reports.finance') }}</li>
    <li class="breadcrumb-item active">{{ __('owner_reports.daily_report') }}</li>
  </ul>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('owner-reports.index') }}" class="row align-items-center">
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">{{ __('owner_reports.from_date') }}</label>
      <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
    </div>
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">{{ __('owner_reports.to_date') }}</label>
      <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
    </div>
    <div class="col-md-2 mt-3">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> {{ __('common.search') }}</button>
    </div>
    <div class="col-md-2 mt-3">
      <a href="{{ route('owner-reports.index') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> {{ __('tables.filters.reset') }}</a>
    </div>
    @if(Auth::user()->role === 'owner')
    <div class="col-md-2 mt-3 text-right">
      <a href="{{ route('settings.index') }}" class="btn btn-outline-dark btn-sm btn-block"><i class="fa fa-gears"></i> {{ __('common.settings') }}</a>
    </div>
    @endif
  </form>
</div>

@if($pendingClosings->isNotEmpty())
<div class="tile d-print-none mb-3 border-warning">
  <h3 class="tile-title text-warning"><i class="fa fa-hourglass-half"></i> {{ __('owner_reports.awaiting_verification', ['count' => $pendingClosings->count()]) }}</h3>
  <div class="tile-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>{{ __('tables.columns.date') }}</th><th>{{ __('owner_reports.submitted_by') }}</th><th>{{ __('tables.columns.collected') }}</th><th>{{ __('tables.columns.action') }}</th></tr></thead>
      <tbody>
        @foreach($pendingClosings as $pending)
        <tr>
          <td><strong>{{ $pending->closing_date->format('M d, Y') }}</strong></td>
          <td>{{ $pending->user->name }}</td>
          <td>TZS {{ number_format($pending->payments_received, 0) }}</td>
          <td>
            <a href="{{ route('day-closing.index', ['date' => $pending->closing_date->format('Y-m-d')]) }}#handover-{{ $pending->id }}" class="btn btn-sm btn-warning">
              <i class="fa fa-check"></i> {{ __('owner_reports.review') }}
            </a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <p class="small text-muted px-3 py-2 mb-0">{{ __('owner_reports.verify_hint') }}</p>
  </div>
</div>
@endif

@if($multiBusiness ?? false)
<div class="tile d-print-none mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2">{{ __('owner_reports.business_filter') }}</span>
    <div class="business-type-tabs mb-2">
      <a href="{{ route('owner-reports.index', request()->except('business_type')) }}"
         class="business-type-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> {{ __('tables.filters.all') }}
      </a>
      @foreach($businessTypes as $type)
        <a href="{{ route('owner-reports.index', array_merge(request()->except('business_type'), ['business_type' => $type['key']])) }}"
           class="business-type-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
          <i class="fa {{ $type['icon'] ?? 'fa-store' }}"></i> {{ $type['label'] }}
        </a>
      @endforeach
    </div>
  </div>
  <p class="small text-muted mb-0">{{ __('owner_reports.multi_business_hint') }}</p>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile p-0" style="overflow:hidden;">
      <div class="table-responsive">
        <table class="table table-bordered excel-table mb-0">
          <thead>
            <tr>
              <th rowspan="2" class="text-center">{{ __('owner_reports.columns.num') }}</th>
              <th rowspan="2">{{ __('owner_reports.columns.date') }}</th>
              @if($multiBusiness ?? false)
              <th rowspan="2">{{ __('owner_reports.columns.business') }}</th>
              @endif
              <th rowspan="2">{{ __('owner_reports.columns.staff') }}</th>
              <th rowspan="2" class="text-center">{{ __('owner_reports.columns.status') }}</th>
              <th rowspan="2" class="text-right">{{ __('owner_reports.columns.opening_cash') }}</th>
              <th colspan="3" class="text-center">{{ __('owner_reports.columns.submitted_collections') }}</th>
              <th rowspan="2" class="text-right bg-secondary text-white">{{ __('owner_reports.columns.assets') }}</th>
              <th rowspan="2" class="text-right">{{ __('owner_reports.columns.expenses') }}</th>
              <th rowspan="2" class="text-right text-success">{{ __('owner_reports.columns.daily_profit') }}</th>
              <th rowspan="2" class="text-right text-info">{{ __('owner_reports.columns.circulation_refill') }}</th>
              <th colspan="2" class="text-center" style="background:#5a6268 !important;">{{ __('owner_reports.columns.short_recovery') }}</th>
              <th rowspan="2" class="text-right text-info">{{ __('owner_reports.columns.circulation_rollover') }}</th>
              <th rowspan="2" class="text-right text-success">{{ __('owner_reports.columns.profit_rollover') }}</th>
              <th rowspan="2" class="text-center d-print-none">{{ __('owner_reports.columns.action') }}</th>
            </tr>
            <tr>
              <th class="text-right">{{ __('owner_reports.columns.cash') }}</th>
              <th class="text-right">{{ __('owner_reports.columns.digital') }}</th>
              <th class="text-right">{{ __('owner_reports.columns.total') }}</th>
              <th class="text-right text-success">{{ __('owner_reports.columns.to_profit') }}</th>
              <th class="text-right text-primary">{{ __('owner_reports.columns.to_circulation') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($ledgers as $index => $ledger)
              @php
                $rowClass = ($ledger['is_manager_received'] ?? false) ? 'manager-received' : '';
                $isPlaceholder = $ledger['is_placeholder'] ?? false;
                $closingRouteId = $ledger['detail_closing_id'] ?? $ledger['id'];
                $showRollover = $ledger['show_rollover_columns'] ?? true;
                $colspan = ($multiBusiness ?? false) ? 18 : 17;
              @endphp
              @if($isPlaceholder)
              <tr class="main-row table-info {{ ($ledger['has_open_day_activity'] ?? false) ? '' : '' }}" @if($ledger['has_open_day_activity'] ?? false) data-toggle="collapse" data-target="#details-{{ $ledger['id'] }}" aria-expanded="false" @endif>
                <td class="text-center text-muted">
                  @if($ledger['has_open_day_activity'] ?? false)
                    <i class="fa fa-chevron-down"></i>
                  @else
                    <i class="fa fa-sun-o"></i>
                  @endif
                </td>
                <td class="font-weight-bold text-primary">{{ \Carbon\Carbon::parse($ledger['ledger_date'])->format('d M, Y') }}</td>
                @if($multiBusiness ?? false)
                <td class="text-muted">—</td>
                @endif
                <td class="text-muted small">{{ ($ledger['has_open_shift'] ?? false) ? __('owner_reports.open_day') : __('owner_reports.awaiting_shift') }}</td>
                <td class="text-center">
                  <span class="status-badge" style="border: 1px solid {{ $ledger['status_color'] }}; color: {{ $ledger['status_color'] }};">
                    {{ __report_status($ledger['business_status']) }}
                  </span>
                </td>
                <td class="money-column font-weight-bold">{{ number_format($ledger['opening_cash'], 0) }}</td>
                <td class="money-column {{ ($ledger['total_cash_received'] ?? 0) > 0 ? 'font-weight-bold' : 'text-muted' }}">
                  {{ ($ledger['total_cash_received'] ?? 0) > 0 ? number_format($ledger['total_cash_received'], 0) : '—' }}
                </td>
                <td class="money-column {{ ($ledger['total_digital_received'] ?? 0) > 0 ? '' : 'text-muted' }}">
                  {{ ($ledger['total_digital_received'] ?? 0) > 0 ? number_format($ledger['total_digital_received'], 0) : '—' }}
                </td>
                <td class="money-column {{ ($ledger['sub_total'] ?? 0) > 0 ? 'font-weight-bold' : 'text-muted' }}">
                  {{ ($ledger['sub_total'] ?? 0) > 0 ? number_format($ledger['sub_total'], 0) : '—' }}
                </td>
                <td class="money-column font-weight-bold bg-light">{{ number_format($ledger['total_assets'], 0) }}</td>
                <td class="money-column {{ ($ledger['combined_expenses'] ?? 0) > 0 ? 'text-danger' : 'text-muted' }}">
                  {{ ($ledger['combined_expenses'] ?? 0) > 0 ? '('.number_format($ledger['combined_expenses'], 0).')' : '—' }}
                </td>
                <td class="money-column {{ ($ledger['daily_net_profit'] ?? 0) != 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                  {{ ($ledger['daily_net_profit'] ?? 0) != 0 ? number_format($ledger['daily_net_profit'], 0) : '—' }}
                </td>
                <td class="money-column {{ ($ledger['circulation_refill'] ?? 0) > 0 ? 'text-info font-weight-bold' : 'text-muted' }}">
                  {{ ($ledger['circulation_refill'] ?? 0) > 0 ? number_format($ledger['circulation_refill'], 0) : '—' }}
                </td>
                <td class="money-column {{ ($ledger['staff_profit_recoveries'] ?? 0) > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                  @if(($ledger['staff_profit_recoveries'] ?? 0) > 0)
                    +{{ number_format($ledger['staff_profit_recoveries'], 0) }}
                  @else
                    —
                  @endif
                </td>
                <td class="money-column {{ ($ledger['staff_circulation_recoveries'] ?? 0) > 0 ? 'text-primary font-weight-bold' : 'text-muted' }}">
                  @if(($ledger['staff_circulation_recoveries'] ?? 0) > 0)
                    +{{ number_format($ledger['staff_circulation_recoveries'], 0) }}
                  @else
                    —
                  @endif
                </td>
                <td class="money-column font-weight-bold text-primary">
                  {{ number_format($ledger['money_in_circulation'], 0) }}
                  <br><span class="badge badge-light text-muted border mt-1" style="font-size:0.6rem;">{{ __('owner_reports.available') }}</span>
                </td>
                <td class="money-column font-weight-bold text-success">
                  {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}
                  <br><span class="badge badge-light text-muted border mt-1" style="font-size:0.6rem;">{{ __('owner_reports.available') }}</span>
                </td>
                <td class="text-center d-print-none">
                  <a href="{{ route('petty-cash.index', ['date' => $ledger['ledger_date']]) }}" class="btn btn-sm btn-outline-primary mr-1" title="{{ __('owner_reports.petty_cash') }}">
                    <i class="fa fa-money"></i>
                  </a>
                  <a href="{{ route('day-closing.index') }}" class="btn btn-sm btn-warning" title="{{ __('owner_reports.awaiting_handover') }}">
                    <i class="fa fa-clock-o"></i>
                  </a>
                </td>
              </tr>
              @if($ledger['has_open_day_activity'] ?? false)
              <tr id="details-{{ $ledger['id'] }}" class="collapse detail-row">
                <td colspan="{{ $colspan }}">
                  <div class="detail-container">
                    <div class="row">
                      <div class="col-md-6 border-right">
                        <h6 class="text-danger"><i class="fa fa-minus-circle"></i> {{ __('owner_reports.sections.daily_expenditures') }}</h6>
                        <table class="table table-sm nested-table mt-2">
                          <thead>
                            <tr><th>{{ __('tables.columns.description') }}</th><th class="text-right">{{ __('owner_reports.columns.amount') }}</th></tr>
                          </thead>
                          <tbody>
                            @forelse($ledger['expense_list'] as $ex)
                              <tr>
                                <td>{{ $ex['description'] }} <small class="text-muted">({{ $ex['category'] }})</small></td>
                                <td class="text-right font-weight-bold">
                                  TZS {{ number_format($ex['amount'], 0) }}
                                  <span class="badge {{ $ex['fund_source'] === 'profit' ? 'badge-info' : 'badge-secondary' }} small" style="font-size:0.6rem;">
                                    {{ __('owner_reports.fund.'.$ex['fund_source']) }}
                                  </span>
                                </td>
                              </tr>
                            @empty
                              <tr><td colspan="2" class="text-center text-muted">{{ __('owner_reports.empty.expenses_today') }}</td></tr>
                            @endforelse
                          </tbody>
                        </table>
                      </div>
                      <div class="col-md-6">
                        <h6 class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.sections.open_day_summary') }}</h6>
                        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.opening_circulation') }}</span><strong>TZS {{ number_format($ledger['opening_cash'], 0) }}</strong></p>
                        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.opening_profit') }}</span><strong>TZS {{ number_format($ledger['opening_profit'] ?? 0, 0) }}</strong></p>
                        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.petty_cash_expenses') }}</span><strong class="text-danger">TZS {{ number_format($ledger['combined_expenses'], 0) }}</strong></p>
                        <p class="mb-2 d-flex justify-content-between border-top pt-2"><span class="text-primary">{{ __('owner_reports.labels.circulation_available') }}</span><strong class="text-primary">TZS {{ number_format($ledger['money_in_circulation'], 0) }}</strong></p>
                        <p class="mb-0 d-flex justify-content-between"><span class="text-success">{{ __('owner_reports.labels.profit_rollover') }}</span><strong class="text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</strong></p>
                        <small class="text-muted d-block mt-2">{{ __('owner_reports.labels.open_day_note') }}</small>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              @endif
              @else
              <tr class="main-row {{ $rowClass }} {{ ($ledger['is_business_type_row'] ?? false) ? 'business-type-row' : '' }}" data-toggle="collapse" data-target="#details-{{ $ledger['id'] }}" aria-expanded="false">
                <td class="text-center text-muted"><i class="fa fa-chevron-down"></i></td>
                <td class="font-weight-bold text-primary">{{ \Carbon\Carbon::parse($ledger['ledger_date'])->format('d M, Y') }}</td>
                @if($multiBusiness ?? false)
                <td><strong>{{ $ledger['business_type_label'] ?? '—' }}</strong></td>
                @endif
                <td class="font-weight-bold">{{ $ledger['handover_label'] ?? $ledger['submitted_by'] ?? __('owner_reports.staff') }}</td>
                <td class="text-center">
                  <span class="status-badge" style="border: 1px solid {{ $ledger['status_color'] }}; color: {{ $ledger['status_color'] }};">
                    {{ __report_status($ledger['business_status']) }}
                  </span>
                </td>
                <td class="money-column">{{ $showRollover && $ledger['opening_cash'] !== null ? number_format($ledger['opening_cash'], 0) : '—' }}</td>
                <td class="money-column font-weight-bold">{{ number_format($ledger['total_cash_received'], 0) }}</td>
                <td class="money-column">{{ number_format($ledger['total_digital_received'], 0) }}</td>
                <td class="money-column font-weight-bold">{{ number_format($ledger['sub_total'], 0) }}</td>
                <td class="money-column font-weight-bold bg-light">{{ number_format($ledger['total_assets'], 0) }}</td>
                <td class="money-column text-danger">({{ number_format($ledger['combined_expenses'], 0) }})</td>
                <td class="money-column text-success font-weight-bold">{{ number_format($ledger['daily_net_profit'] ?? $ledger['net_available_profit'], 0) }}</td>
                <td class="money-column text-info font-weight-bold">{{ number_format($ledger['circulation_refill'], 0) }}</td>
                <td class="money-column {{ ($ledger['staff_profit_recoveries'] ?? 0) > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                  @if(($ledger['staff_profit_recoveries'] ?? 0) > 0)
                    +{{ number_format($ledger['staff_profit_recoveries'], 0) }}
                  @else
                    —
                  @endif
                </td>
                <td class="money-column {{ ($ledger['staff_circulation_recoveries'] ?? 0) > 0 ? 'text-primary font-weight-bold' : 'text-muted' }}">
                  @if(($ledger['staff_circulation_recoveries'] ?? 0) > 0)
                    +{{ number_format($ledger['staff_circulation_recoveries'], 0) }}
                  @else
                    —
                  @endif
                </td>
                <td class="money-column font-weight-bold">
                  @if($showRollover && $ledger['money_in_circulation'] !== null)
                  {{ number_format($ledger['money_in_circulation'], 0) }}
                  @if($ledger['is_finalized'])
                    <br><span class="status-badge text-success mt-1 d-inline-block" style="border-color:#28a745;"><i class="fa fa-check-circle"></i> {{ __('owner_reports.finalized') }}</span>
                  @else
                    <br><span class="badge badge-light text-muted border mt-1" style="font-size:0.6rem;">{{ __('owner_reports.available') }}</span>
                  @endif
                  @else
                  <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="money-column font-weight-bold text-success">
                  @if($showRollover && $ledger['profit_rollover'] !== null)
                  {{ number_format($ledger['profit_rollover'], 0) }}
                  @if($ledger['is_finalized'])
                    <br><span class="status-badge text-success mt-1 d-inline-block" style="border-color:#28a745;"><i class="fa fa-check-circle"></i> {{ __('owner_reports.finalized') }}</span>
                  @else
                    <br><span class="badge badge-light text-muted border mt-1" style="font-size:0.6rem;">{{ __('owner_reports.available') }}</span>
                  @endif
                  @else
                  <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-center d-print-none" style="white-space:nowrap;">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('day-closing.show', $closingRouteId) }}" class="btn btn-primary shadow-sm" title="{{ __('owner_reports.view_reconciliation') }}" onclick="event.stopPropagation();">
                      <i class="fa fa-eye"></i>
                    </a>
                    <a href="{{ route('day-closing.show', $closingRouteId) }}" target="_blank" class="btn btn-dark shadow-sm" title="{{ __('owner_reports.print') }}" onclick="event.stopPropagation();">
                      <i class="fa fa-print"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <tr id="details-{{ $ledger['id'] }}" class="collapse detail-row">
                <td colspan="{{ $colspan }}">
                  <div class="detail-container">
                    <div class="row">
                      <div class="col-md-6 border-right">
                        <h6 class="text-danger"><i class="fa fa-minus-circle"></i> {{ __('owner_reports.sections.daily_expenditures') }}</h6>
                        <table class="table table-sm nested-table mt-2">
                          <thead>
                            <tr><th>{{ __('tables.columns.description') }}</th><th class="text-right">{{ __('owner_reports.columns.amount') }}</th></tr>
                          </thead>
                          <tbody>
                            @forelse($ledger['expense_list'] as $ex)
                              <tr>
                                <td>{{ $ex['description'] }} <small class="text-muted">({{ $ex['category'] }})</small></td>
                                <td class="text-right font-weight-bold">
                                  TZS {{ number_format($ex['amount'], 0) }}
                                  <span class="badge {{ $ex['fund_source'] === 'profit' ? 'badge-info' : 'badge-secondary' }} small" style="font-size:0.6rem;">
                                    {{ __('owner_reports.fund.'.$ex['fund_source']) }}
                                  </span>
                                </td>
                              </tr>
                            @empty
                              <tr><td colspan="2" class="text-center text-muted">{{ __('owner_reports.empty.expenses') }}</td></tr>
                            @endforelse
                            <tr class="bg-light">
                              <th class="text-right">{{ __('owner_reports.labels.total_outflow') }}</th>
                              <th class="text-right text-danger">TZS {{ number_format($ledger['combined_expenses'], 0) }}</th>
                            </tr>
                          </tbody>
                        </table>

                        <h6 class="mt-4 text-primary"><i class="fa fa-credit-card"></i> {{ __('owner_reports.sections.collections_by_platform') }}</h6>
                        <table class="table table-sm nested-table mt-2">
                          <thead><tr><th>{{ __('tables.columns.platform') }}</th><th class="text-right">{{ __('owner_reports.columns.amount') }}</th></tr></thead>
                          <tbody>
                            @foreach($ledger['platform_breakdown'] as $key => $platform)
                              @php $amt = is_array($platform) ? ($platform['amount'] ?? 0) : $platform; @endphp
                              @if($amt != 0)
                              <tr>
                                <td>{{ is_array($platform) ? ($platform['label'] ?? ucwords(str_replace('_', ' ', $key))) : ucwords(str_replace('_', ' ', $key)) }}</td>
                                <td class="text-right">TZS {{ number_format($amt, 0) }}</td>
                              </tr>
                              @endif
                            @endforeach
                          </tbody>
                        </table>

                        @if($ledger['outstanding_debt'] > 0)
                        <div class="mt-3 border-top pt-3">
                          <h6 class="text-danger font-weight-bold" style="font-size:0.8rem;"><i class="fa fa-exclamation-triangle"></i> {{ __('owner_reports.sections.new_debt_unpaid') }}</h6>
                          <p class="mb-0 font-weight-bold text-danger">TZS {{ number_format($ledger['outstanding_debt'], 0) }}</p>
                        </div>
                        @endif

                        @if(($multiBusiness ?? false) && !empty($ledger['business_type_breakdown']) && !($ledger['is_business_type_row'] ?? false))
                        <div class="mt-4 border-top pt-3">
                          <h6 class="text-primary"><i class="fa fa-sitemap"></i> {{ __('owner_reports.sections.by_business_type') }}</h6>
                          <table class="table table-sm nested-table mt-2">
                            <thead>
                              <tr>
                                <th>{{ __('tables.columns.business') }}</th>
                                <th class="text-right">{{ __('owner_reports.columns.collected') }}</th>
                                <th class="text-right">{{ __('owner_reports.columns.new_debt') }}</th>
                                <th class="text-right">{{ __('owner_reports.columns.profit') }}</th>
                                <th class="text-right">{{ __('owner_reports.columns.circulation') }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              @foreach($ledger['business_type_breakdown'] as $typeRow)
                              <tr>
                                <td>{{ $typeRow['label'] }}</td>
                                <td class="text-right">TZS {{ number_format($typeRow['collected'], 0) }}</td>
                                <td class="text-right text-danger">{{ $typeRow['credit'] > 0 ? 'TZS '.number_format($typeRow['credit'], 0) : '—' }}</td>
                                <td class="text-right text-success">TZS {{ number_format($typeRow['profit_generated'], 0) }}</td>
                                <td class="text-right text-info">TZS {{ number_format($typeRow['circulation_generated'], 0) }}</td>
                              </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                        @endif
                      </div>

                      <div class="col-md-6">
                        <h6 class="text-success"><i class="fa fa-info-circle"></i> {{ __('owner_reports.sections.reconciliation_summary') }}</h6>
                        <div class="mt-3">
                          <p class="mb-1 d-flex justify-content-between">
                            <span>{{ __('owner_reports.metrics.gross_sales') }}</span>
                            <span class="font-weight-bold">TZS {{ number_format($ledger['gross_sales'], 0) }}</span>
                          </p>
                          <p class="mb-1 d-flex justify-content-between">
                            <span>{{ __('owner_reports.metrics.cost_of_goods') }}</span>
                            <span class="text-muted">(-) TZS {{ number_format($ledger['cost_of_goods'], 0) }}</span>
                          </p>
                          <p class="mb-1 d-flex justify-content-between border-bottom pb-1">
                            <span>{{ __('owner_reports.metrics.gross_profit') }}</span>
                            <span class="font-weight-bold text-success">TZS {{ number_format($ledger['profit_generated'], 0) }}</span>
                          </p>
                          <p class="mb-1 d-flex justify-content-between">
                            <span>{{ __('owner_reports.metrics.total_collected') }}</span>
                            <span class="font-weight-bold">TZS {{ number_format($ledger['sub_total'], 0) }}</span>
                          </p>
                          @if(($ledger['money_short_recoveries'] ?? 0) > 0)
                          <p class="mb-1 d-flex justify-content-between">
                            <span>{{ __('owner_reports.labels.money_short_recoveries') }}</span>
                            <span class="font-weight-bold text-primary">+ TZS {{ number_format($ledger['money_short_recoveries'], 0) }}</span>
                          </p>
                          @if(($ledger['money_short_profit_recoveries'] ?? 0) > 0 || ($ledger['money_short_circulation_recoveries'] ?? 0) > 0)
                          <p class="mb-1 pl-3 small d-flex justify-content-between">
                            <span class="text-muted">{{ __('owner_reports.labels.to_profit') }}</span>
                            <span class="text-success">+ TZS {{ number_format($ledger['money_short_profit_recoveries'] ?? 0, 0) }}</span>
                          </p>
                          <p class="mb-1 pl-3 small d-flex justify-content-between">
                            <span class="text-muted">{{ __('owner_reports.labels.to_circulation') }}</span>
                            <span class="text-primary">+ TZS {{ number_format($ledger['money_short_circulation_recoveries'] ?? 0, 0) }}</span>
                          </p>
                          @endif
                          <p class="mb-1 text-muted small">{{ __('owner_reports.labels.short_recovery_note') }}</p>
                          @endif
                          <p class="mb-1 d-flex justify-content-between">
                            <span>{{ __('owner_reports.labels.total_expenses') }}</span>
                            <span class="text-danger">(-) TZS {{ number_format($ledger['combined_expenses'], 0) }}</span>
                          </p>
                          <p class="mb-3 d-flex justify-content-between h6">
                            <span>{{ __('owner_reports.labels.net_profit_today') }}</span>
                            <span class="font-weight-bold text-success">TZS {{ number_format($ledger['net_available_profit'], 0) }}</span>
                          </p>
                          <p class="mb-3 d-flex justify-content-between h6 border-bottom pb-2">
                            <span>{{ __('owner_reports.labels.opening_profit') }}</span>
                            <span class="font-weight-bold">TZS {{ number_format($ledger['opening_profit'] ?? 0, 0) }}</span>
                          </p>
                          <p class="mb-3 d-flex justify-content-between h5">
                            <span class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.labels.total_profit_rollover') }}</span>
                            <span class="font-weight-bold text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</span>
                          </p>

                          <div class="alert alert-info py-2" style="font-size:0.85rem; border-left: 5px solid #940000;">
                            <strong>{{ __('owner_reports.sections.financial_breakdown') }}</strong>
                            <div class="mt-2 pl-2">
                              <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('owner_reports.labels.submitted_by') }}</span>
                                <span class="font-weight-bold">{{ $ledger['submitted_by'] }}</span>
                              </div>
                              <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('owner_reports.labels.circulation_refill_capital') }}</span>
                                <span class="font-weight-bold text-info">TZS {{ number_format($ledger['circulation_refill'], 0) }}</span>
                              </div>
                              <div class="d-flex justify-content-between mb-1">
                                <span>{{ __('owner_reports.labels.expenses_deduct_from') }}</span>
                                <span class="font-weight-bold">{{ __('owner_reports.labels.'.$ledger['expense_deduct_from']) }}</span>
                              </div>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between font-weight-bold">
                              <span class="text-primary"><i class="fa fa-clock-o"></i> {{ __('owner_reports.labels.circulation_next_day') }}</span>
                              <span class="h6 mb-0 text-primary">TZS {{ number_format($ledger['carried_forward'], 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between font-weight-bold mt-2">
                              <span class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.labels.profit_next_day') }}</span>
                              <span class="h6 mb-0 text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</span>
                            </div>
                            <small class="text-muted d-block mt-1">{{ __('owner_reports.labels.finalize_note') }}</small>
                          </div>

                          <div class="text-right mt-3">
                            <a href="{{ route('day-closing.show', $closingRouteId) }}" class="btn btn-primary btn-sm">
                              <i class="fa fa-external-link"></i> {{ __('owner_reports.view_reconciliation') }}
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              @endif
            @empty
              <tr><td colspan="{{ ($multiBusiness ?? false) ? 18 : 17 }}" class="text-center py-5 text-muted">
                @if(!empty($canSwitchBranch) && empty($viewingAllBranches))
                  {{ __('owner_reports.empty.reports_branch', ['branch' => $activeBranchLabel ?? __('common.branch')]) }}
                @else
                  {{ __('owner_reports.empty.reports') }}
                @endif
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-3 d-print-none d-flex justify-content-center pb-3">
        {{ $closings->links('pagination::bootstrap-4') }}
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  $('.main-row').on('click', function() {
    const expanded = $(this).attr('aria-expanded') === 'true';
    $(this).attr('aria-expanded', expanded ? 'false' : 'true');
  });
});
</script>
@endsection
