@php
  $closingRouteId = $closingRouteId ?? ($ledger['detail_closing_id'] ?? $ledger['id']);
@endphp
<div class="detail-container">
  @if($ledger['is_placeholder'] ?? false)
    <div class="row">
      <div class="col-12 col-md-6 mb-3 mb-md-0 or-detail-col-border">
        <h6 class="text-danger"><i class="fa fa-minus-circle"></i> {{ __('owner_reports.sections.daily_expenditures') }}</h6>
        <table class="table table-sm nested-table mt-2">
          <thead><tr><th>{{ __('tables.columns.description') }}</th><th class="text-right">{{ __('owner_reports.columns.amount') }}</th></tr></thead>
          <tbody>
            @forelse($ledger['expense_list'] as $ex)
              <tr>
                <td>{{ $ex['description'] }} <small class="text-muted">({{ $ex['category'] }})</small></td>
                <td class="text-right font-weight-bold">TZS {{ number_format($ex['amount'], 0) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">{{ __('owner_reports.empty.expenses_today') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="col-12 col-md-6">
        <h6 class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.sections.open_day_summary') }}</h6>
        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.opening_circulation') }}</span><strong>TZS {{ number_format($ledger['opening_cash'], 0) }}</strong></p>
        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.opening_profit') }}</span><strong>TZS {{ number_format($ledger['opening_profit'] ?? 0, 0) }}</strong></p>
        <p class="mb-2 d-flex justify-content-between"><span>{{ __('owner_reports.labels.petty_cash_expenses') }}</span><strong class="text-danger">TZS {{ number_format($ledger['combined_expenses'], 0) }}</strong></p>
        <p class="mb-2 d-flex justify-content-between border-top pt-2"><span class="text-primary">{{ __('owner_reports.labels.circulation_available') }}</span><strong class="text-primary">TZS {{ number_format($ledger['money_in_circulation'], 0) }}</strong></p>
        <p class="mb-0 d-flex justify-content-between"><span class="text-success">{{ __('owner_reports.labels.profit_rollover') }}</span><strong class="text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</strong></p>
        <small class="text-muted d-block mt-2">{{ __('owner_reports.labels.open_day_note') }}</small>
      </div>
    </div>
  @else
    <div class="row">
      <div class="col-12 col-md-6 mb-3 mb-md-0 or-detail-col-border">
        <h6 class="text-danger"><i class="fa fa-minus-circle"></i> {{ __('owner_reports.sections.daily_expenditures') }}</h6>
        <table class="table table-sm nested-table mt-2">
          <thead><tr><th>{{ __('tables.columns.description') }}</th><th class="text-right">{{ __('owner_reports.columns.amount') }}</th></tr></thead>
          <tbody>
            @forelse($ledger['expense_list'] as $ex)
              <tr>
                <td>{{ $ex['description'] }} <small class="text-muted">({{ $ex['category'] }})</small></td>
                <td class="text-right font-weight-bold">
                  TZS {{ number_format($ex['amount'], 0) }}
                  <span class="badge {{ $ex['fund_source'] === 'profit' ? 'badge-info' : 'badge-secondary' }} small" style="font-size:0.6rem;">{{ __('owner_reports.fund.'.$ex['fund_source']) }}</span>
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

      <div class="col-12 col-md-6">
        <h6 class="text-success"><i class="fa fa-info-circle"></i> {{ __('owner_reports.sections.reconciliation_summary') }}</h6>
        <div class="mt-3">
          <p class="mb-1 d-flex justify-content-between"><span>{{ __('owner_reports.metrics.gross_sales') }}</span><span class="font-weight-bold">TZS {{ number_format($ledger['gross_sales'], 0) }}</span></p>
          <p class="mb-1 d-flex justify-content-between"><span>{{ __('owner_reports.metrics.cost_of_goods') }}</span><span class="text-muted">(-) TZS {{ number_format($ledger['cost_of_goods'], 0) }}</span></p>
          <p class="mb-1 d-flex justify-content-between border-bottom pb-1"><span>{{ __('owner_reports.metrics.gross_profit') }}</span><span class="font-weight-bold text-success">TZS {{ number_format($ledger['profit_generated'], 0) }}</span></p>
          <p class="mb-1 d-flex justify-content-between"><span>{{ __('owner_reports.metrics.total_collected') }}</span><span class="font-weight-bold">TZS {{ number_format($ledger['sub_total'], 0) }}</span></p>
          @if(($ledger['money_short_recoveries'] ?? 0) > 0)
          <p class="mb-1 d-flex justify-content-between"><span>{{ __('owner_reports.labels.money_short_recoveries') }}</span><span class="font-weight-bold text-primary">+ TZS {{ number_format($ledger['money_short_recoveries'], 0) }}</span></p>
          @if(($ledger['money_short_profit_recoveries'] ?? 0) > 0 || ($ledger['money_short_circulation_recoveries'] ?? 0) > 0)
          <p class="mb-1 pl-3 small d-flex justify-content-between"><span class="text-muted">{{ __('owner_reports.labels.to_profit') }}</span><span class="text-success">+ TZS {{ number_format($ledger['money_short_profit_recoveries'] ?? 0, 0) }}</span></p>
          <p class="mb-1 pl-3 small d-flex justify-content-between"><span class="text-muted">{{ __('owner_reports.labels.to_circulation') }}</span><span class="text-primary">+ TZS {{ number_format($ledger['money_short_circulation_recoveries'] ?? 0, 0) }}</span></p>
          @endif
          <p class="mb-1 text-muted small">{{ __('owner_reports.labels.short_recovery_note') }}</p>
          @endif
          <p class="mb-1 d-flex justify-content-between"><span>{{ __('owner_reports.labels.total_expenses') }}</span><span class="text-danger">(-) TZS {{ number_format($ledger['combined_expenses'], 0) }}</span></p>
          <p class="mb-3 d-flex justify-content-between h6"><span>{{ __('owner_reports.labels.net_profit_today') }}</span><span class="font-weight-bold text-success">TZS {{ number_format($ledger['net_available_profit'], 0) }}</span></p>
          <p class="mb-3 d-flex justify-content-between h6 border-bottom pb-2"><span>{{ __('owner_reports.labels.opening_profit') }}</span><span class="font-weight-bold">TZS {{ number_format($ledger['opening_profit'] ?? 0, 0) }}</span></p>
          <p class="mb-3 d-flex justify-content-between h5"><span class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.labels.total_profit_rollover') }}</span><span class="font-weight-bold text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</span></p>

          <div class="alert alert-info py-2 or-detail-alert">
            <strong>{{ __('owner_reports.sections.financial_breakdown') }}</strong>
            <div class="mt-2 pl-2">
              <div class="d-flex justify-content-between mb-1"><span>{{ __('owner_reports.labels.submitted_by') }}</span><span class="font-weight-bold">{{ $ledger['submitted_by'] }}</span></div>
              <div class="d-flex justify-content-between mb-1"><span>{{ __('owner_reports.labels.circulation_refill_capital') }}</span><span class="font-weight-bold text-info">TZS {{ number_format($ledger['circulation_refill'], 0) }}</span></div>
              <div class="d-flex justify-content-between mb-1"><span>{{ __('owner_reports.labels.expenses_deduct_from') }}</span><span class="font-weight-bold">{{ __('owner_reports.labels.'.$ledger['expense_deduct_from']) }}</span></div>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between font-weight-bold"><span class="text-primary"><i class="fa fa-clock-o"></i> {{ __('owner_reports.labels.circulation_next_day') }}</span><span class="h6 mb-0 text-primary">TZS {{ number_format($ledger['carried_forward'], 0) }}</span></div>
            <div class="d-flex justify-content-between font-weight-bold mt-2"><span class="text-success"><i class="fa fa-line-chart"></i> {{ __('owner_reports.labels.profit_next_day') }}</span><span class="h6 mb-0 text-success">TZS {{ number_format($ledger['profit_rollover'] ?? 0, 0) }}</span></div>
          </div>

          <div class="or-detail-actions">
            <a href="{{ route('day-closing.show', $closingRouteId) }}" class="btn btn-primary btn-sm mb-2 mr-2"><i class="fa fa-eye"></i> {{ __('owner_reports.view_reconciliation') }}</a>
            <a href="{{ route('day-closing.index', ['date' => $ledger['ledger_date']]) }}#{{ ($ledger['shift_id'] ?? null) ? 'handover-'.$closingRouteId : 'owner-day-close' }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-external-link"></i> {{ __('owner_reports.view_reconciliation') }}</a>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
