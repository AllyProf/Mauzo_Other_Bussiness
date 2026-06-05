<div class="tile mb-4 handover-card" id="handover-{{ $dayClosing->id }}">
  <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
    <div>
      <h3 class="tile-title mb-1">
        <i class="fa fa-user"></i> {{ $dayClosing->user->name ?? 'Unknown' }}
        @if($dayClosing->shift)
          <small class="text-muted">· Shift #{{ $dayClosing->shift->id }}</small>
        @endif
      </h3>
      <p class="mb-0 text-muted">
        Submitted {{ $dayClosing->submitted_at?->format('M d, Y h:i A') ?? '—' }}
        @if($dayClosing->shift?->closed_at)
          · Shift closed {{ $dayClosing->shift->closed_at->format('M d, Y h:i A') }}
        @endif
      </p>
    </div>
    <div class="mt-2 mt-md-0">
      @if($dayClosing->status === 'verified')
        <span class="badge badge-success badge-lg p-2">Verified</span>
      @elseif($dayClosing->status === 'disputed')
        <span class="badge badge-danger badge-lg p-2">Disputed</span>
      @else
        <span class="badge badge-warning badge-lg p-2">Awaiting verification</span>
      @endif
    </div>
  </div>

  @if($dayClosing->status === 'verified')
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> Verified by {{ $dayClosing->verifier->name ?? 'Boss' }} on {{ $dayClosing->verified_at?->format('M d, Y h:i A') }}</div>
  @elseif($dayClosing->status === 'disputed')
    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <strong>Disputed:</strong> {{ $dayClosing->dispute_reason }}</div>
  @elseif($canVerifyHandover ?? false)
    <div class="alert alert-warning"><i class="fa fa-clock-o"></i> Review collections below, then verify to post debt, profit, and circulation to the Master Sheet.</div>
  @elseif($dayClosing->status === 'submitted')
    <div class="alert alert-warning"><i class="fa fa-clock-o"></i> <strong>Awaiting boss verification.</strong> Your handover has been submitted successfully.</div>
  @endif

  @include('day-closing.partials.final-handover-summary', ['handoverSummary' => $handoverSummary ?? [
    'gross_collected' => (float) ($dayClosing->net_amount + $dayClosing->total_expenses),
    'expenses' => (float) $dayClosing->total_expenses,
    'final_handover' => (float) $dayClosing->net_amount,
    'debt_collected' => (float) ($debtCollections['total'] ?? 0),
  ]])

  @unless($canViewBossFinancials ?? false)
  <div class="row mb-3">
    <div class="col-md-3 col-6"><div class="widget-small primary coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i><div class="info"><h4>Sales</h4><p><b>{{ $dayClosing->sales_count }}</b></p></div></div></div>
    <div class="col-md-3 col-6"><div class="widget-small info coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i><div class="info"><h4>Gross</h4><p><b>{{ money($dayClosing->gross_sales) }}</b></p></div></div></div>
    <div class="col-md-3 col-6"><div class="widget-small warning coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>Submitted</h4><p><b>{{ money($dayClosing->payments_received) }}</b></p></div></div></div>
    <div class="col-md-3 col-6"><div class="widget-small success coloured-icon"><i class="icon fa fa-check fa-3x"></i><div class="info text-dark"><h4>Net Handover</h4><p><b>{{ money($dayClosing->net_amount) }}</b></p></div></div></div>
  </div>
  @endunless

  <h5 class="d-flex justify-content-between align-items-center flex-wrap">
    <span>Staff Reconciliation</span>
    <button type="button" class="btn btn-info btn-sm view-handover-sales-btn mt-2 mt-md-0" data-sales='@json($allDaySales)' data-title="All Sales — {{ $dayClosing->user->name ?? 'Staff' }}">
      <i class="fa fa-eye"></i> View All Sales ({{ count($allDaySales) }})
    </button>
  </h5>
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-sm">
      <thead>
        <tr>
          <th>{{ __('tables.columns.staff') }}</th><th>Orders</th><th>{{ __('tables.columns.gross') }}</th><th>Cash</th><th>Mobile</th><th>Bank</th><th class="audit-col-bg">Debt Paid</th>
          <th class="audit-col-bg">Expected</th><th class="audit-col-bg">Collected</th><th class="diff-col-bg">Diff</th><th>{{ __('tables.columns.status') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($staffRows as $data)
        <tr>
          <td>{{ $data['staff']->name ?? 'Unknown' }}</td>
          <td>{{ $data['total_orders'] }}</td>
          <td>{{ money($data['gross_sales']) }}</td>
          <td>{{ money($data['cash_collected']) }}</td>
          <td>{{ money($data['mobile_collected']) }}</td>
          <td>{{ money($data['bank_collected']) }}</td>
          <td class="audit-col-bg">{{ ($data['debt_collected'] ?? 0) > 0 ? money($data['debt_collected']) : '—' }}</td>
          <td class="audit-col-bg">{{ money($data['expected_amount']) }}</td>
          <td class="audit-col-bg">{{ money($data['collected_on_orders']) }}</td>
          <td class="diff-col-bg">
            @if(abs($data['difference']) < 0.01)
              —
            @else
              {{ $data['difference'] > 0 ? '+' : '' }}{{ number_format($data['difference'], 0) }}
            @endif
          </td>
          <td><span class="status-pill badge-{{ $data['status'] === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($data['status']) }}</span></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if(($debtCollections['count'] ?? 0) > 0)
  <h5 class="mt-2"><i class="fa fa-history"></i> Debt Collections ({{ $debtCollections['count'] }})</h5>
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-sm">
      <thead>
        <tr>
          <th>{{ __('tables.columns.time') }}</th>
          <th>Collected By</th>
          <th>{{ __('tables.columns.customer') }}</th>
          <th>Sale Ref</th>
          <th>Sale Date</th>
          <th>{{ __('tables.columns.amount') }}</th>
          <th>{{ __('tables.columns.method') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($debtCollections['items'] as $item)
          <tr>
            <td>{{ $item['collected_at'] }}</td>
            <td>{{ $item['collected_by'] }}</td>
            <td>{{ $item['customer'] }}</td>
            <td>{{ $item['sale_ref'] }}</td>
            <td>{{ $item['sale_date'] }}</td>
            <td>{{ money($item['amount']) }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $item['method'])) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr><th colspan="5" class="text-right">Total</th><th colspan="2">{{ money($debtCollections['total']) }}</th></tr>
      </tfoot>
    </table>
  </div>
  @endif

  <div class="row">
    <div class="col-md-6">
      <h5>Payment Breakdown (Submitted)</h5>
      <table class="table table-bordered table-sm">
        @php $submittedBreakdown = $dayClosing->payment_breakdown ?? []; @endphp
        @forelse($submittedBreakdown as $key => $amount)
          @if($amount != 0)
          <tr>
            <th>{{ $platformBreakdown[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)) }}</th>
            <td>{{ money($amount) }}</td>
          </tr>
          @endif
        @empty
          <tr><th>Cash</th><td>{{ money($dayClosing->cash_received) }}</td></tr>
          <tr><th>Mobile Money</th><td>{{ money($dayClosing->mobile_received) }}</td></tr>
          <tr><th>Bank</th><td>{{ money($dayClosing->bank_received) }}</td></tr>
        @endforelse
        @if(($handoverSummary['expenses'] ?? $dayClosing->total_expenses) > 0)
          <tr class="table-light">
            <td colspan="2" class="small text-muted py-2">
              <i class="fa fa-info-circle"></i>
              Shift expenses ({{ money($handoverSummary['expenses'] ?? $dayClosing->total_expenses) }}) were deducted from the platforms above before handover.
            </td>
          </tr>
        @endif
        <tr class="table-success">
          <th>Final Amount to Handover</th>
          <td><strong>{{ money($handoverSummary['final_handover'] ?? $dayClosing->net_amount) }}</strong></td>
        </tr>
      </table>
    </div>
    <div class="col-md-6">
      <h5>Expenses</h5>
      @if($dayClosing->expenses->isNotEmpty())
      <table class="table table-bordered table-sm">
        <thead><tr><th>{{ __('tables.columns.description') }}</th><th>{{ __('tables.columns.platform') }}</th><th>{{ __('tables.columns.amount') }}</th></tr></thead>
        <tbody>
          @foreach($dayClosing->expenses as $expense)
            <tr>
              <td>{{ $expense->description }}</td>
              <td>{{ $platformBreakdown[$expense->payment_method]['label'] ?? ucwords(str_replace('_', ' ', $expense->payment_method ?? 'cash')) }}</td>
              <td>{{ money($expense->amount) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot><tr><th colspan="2">Total</th><th>{{ money($dayClosing->total_expenses) }}</th></tr></tfoot>
      </table>
      @else
        <p class="text-muted">No expenses recorded.</p>
      @endif
    </div>
  </div>

  @if($canViewBossFinancials ?? false)
  <div class="border-top pt-4 mt-2">
    <h5><i class="fa fa-calculator"></i> Boss Financial Review</h5>
    <div class="row text-center mt-3 p-3 bg-light rounded">
      <div class="col-md-4 mb-3 mb-md-0">
        <small class="text-uppercase font-weight-bold text-muted">Credit / Debt</small>
        <h4 class="{{ ($financeData['outstanding_debt'] ?? 0) > 0 ? 'text-danger' : 'text-success' }} mb-0">
          {{ money($financeData['outstanding_debt'] ?? 0) }}
        </h4>
        <small class="text-muted">Unpaid sales this shift</small>
      </div>
      <div class="col-md-4 mb-3 mb-md-0" style="border-left: 1px solid #dee2e6;">
        <small class="text-uppercase font-weight-bold text-muted">Profit</small>
        <h4 class="text-success mb-0">{{ money($financeData['net_profit'] ?? 0) }}</h4>
        <small class="text-muted">{{ ($financeData['shift_scoped'] ?? false) ? 'From this shift only' : 'Generated for this day' }}</small>
      </div>
      <div class="col-md-4" style="border-left: 1px solid #dee2e6;">
        <small class="text-uppercase font-weight-bold text-muted">Money in Circulation</small>
        <h4 class="text-primary mb-0">{{ money($financeData['closing_circulation'] ?? 0) }}</h4>
        <small class="text-muted">
          @if($financeData['shift_scoped'] ?? false)
            Net handover {{ money($financeData['net_handover'] ?? 0) }} minus profit
          @else
            Opening {{ money($financeData['opening_circulation'] ?? 0) }}
          @endif
        </small>
      </div>
    </div>
  </div>
  @endif

  @if($dayClosing->report_notes)
    <h5 class="mt-3">Note from Staff</h5>
    <div class="p-3 bg-light rounded">{!! nl2br(e($dayClosing->report_notes)) !!}</div>
  @endif

  @if($dayClosing->hasMoneyShort())
  <div class="alert alert-danger mt-3 mb-0">
    <h5 class="alert-heading mb-2"><i class="fa fa-exclamation-triangle"></i> Money Short Recorded</h5>
    <div class="row">
      <div class="col-md-4"><small class="text-uppercase font-weight-bold">Expected</small><div>{{ money($dayClosing->expectedHandoverAmount()) }}</div></div>
      <div class="col-md-4"><small class="text-uppercase font-weight-bold">Actual Received</small><div>{{ money($dayClosing->actual_received) }}</div></div>
      <div class="col-md-4"><small class="text-uppercase font-weight-bold">Short</small><div class="font-weight-bold">{{ money($dayClosing->money_short) }}</div></div>
    </div>
    @if($dayClosing->shortage_note)
      <hr class="my-2">
      <small class="text-uppercase font-weight-bold">Boss Note</small>
      <div>{{ $dayClosing->shortage_note }}</div>
    @endif
  </div>
  @endif

  @if($canVerifyHandover ?? false)
    @if($dayClosing->status === 'submitted')
    <div class="mt-4 border-top pt-3">
      <h5 class="mb-3"><i class="fa fa-check-square-o"></i> Verify Handover</h5>
      <form action="{{ route('day-closing.verify', $dayClosing) }}" method="POST" class="verify-handover-form" data-closing-id="{{ $dayClosing->id }}">
        @csrf
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="font-weight-bold text-muted small text-uppercase">Expected Handover</label>
            <div class="form-control bg-light font-weight-bold">{{ money($handoverSummary['final_handover'] ?? $dayClosing->net_amount) }}</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="actual-received-{{ $dayClosing->id }}" class="font-weight-bold">Actual Amount Received <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
              <input type="number"
                     id="actual-received-{{ $dayClosing->id }}"
                     name="actual_received"
                     class="form-control actual-received-input font-weight-bold"
                     min="0"
                     step="1"
                     required
                     value="{{ old('actual_received', round($handoverSummary['final_handover'] ?? $dayClosing->net_amount)) }}"
                     data-expected="{{ round($handoverSummary['final_handover'] ?? $dayClosing->net_amount) }}">
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <label class="font-weight-bold text-muted small text-uppercase">Money Short</label>
            <div class="form-control bg-light text-danger font-weight-bold money-short-display" id="money-short-display-{{ $dayClosing->id }}">—</div>
          </div>
        </div>
        <div class="form-group shortage-note-wrap" id="shortage-note-wrap-{{ $dayClosing->id }}" style="display: none;">
          <label for="shortage-note-{{ $dayClosing->id }}" class="font-weight-bold">Shortage Explanation <span class="text-danger">*</span></label>
          <textarea id="shortage-note-{{ $dayClosing->id }}"
                    name="shortage_note"
                    class="form-control"
                    rows="2"
                    placeholder="Explain why the staff handed over less than expected...">{{ old('shortage_note') }}</textarea>
        </div>
        <div class="d-flex flex-wrap align-items-center">
          <button type="submit" class="btn btn-success btn-lg mb-2">
            <i class="fa fa-check"></i> Verify &amp; Post to Master Sheet
          </button>
        </div>
      </form>
    </div>
    @elseif($dayClosing->status === 'verified')
    <div class="mt-4 border-top pt-3 d-flex flex-wrap">
      <a href="{{ route('owner-reports.index') }}" class="btn btn-primary mr-2 mb-2">
        <i class="fa fa-list-alt"></i> Open in Master Sheet
      </a>
      @if($dayClosing->hasMoneyShort())
      <a href="{{ route('money-shorts.index') }}" class="btn btn-outline-danger mb-2">
        <i class="fa fa-money"></i> View Money Shorts
      </a>
      @endif
    </div>
    @endif
  @endif
</div>
