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
    <div class="mt-2 mt-md-0 text-right">
      @if(($verifyQueuePosition ?? null) && ($dayClosing->status ?? null) === 'submitted')
        <span class="badge badge-dark badge-lg p-2 mb-2 d-inline-block">Verify {{ $verifyQueuePosition }} of {{ $verifyQueueTotal }}</span><br>
      @endif
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

  @php
    $shiftStats = $shiftStats ?? [
      'orders' => (int) $dayClosing->sales_count,
      'gross' => (float) $dayClosing->gross_sales,
      'collected' => (float) $dayClosing->amount_collected,
      'unpaid' => max((float) $dayClosing->outstanding_sales, max(0, (float) $dayClosing->gross_sales - (float) $dayClosing->amount_collected)),
      'handover' => (float) $dayClosing->net_amount,
    ];
    $shiftGross = (float) $shiftStats['gross'];
    $shiftCollected = (float) $shiftStats['collected'];
    $shiftUnpaid = (float) $shiftStats['unpaid'];
    $priorShiftOrders = (int) ($shiftStats['prior_shift_orders'] ?? 0);
    $priorShiftCollected = (float) ($shiftStats['prior_shift_collected'] ?? 0);
    $submittedBreakdown = $dayClosing->payment_breakdown ?? [];
    if ($submittedBreakdown === []) {
      $submittedBreakdown = array_filter([
        'cash' => (float) $dayClosing->cash_received,
        'mobile_money' => (float) $dayClosing->mobile_received,
        'bank' => (float) $dayClosing->bank_received,
      ], fn ($amount) => $amount != 0);
    }
  @endphp

  <h5 class="d-flex justify-content-between align-items-center flex-wrap mt-3 mb-2">
    <span>Shift Summary</span>
    @if(count($allDaySales) > 0 || ($debtCollections['count'] ?? 0) > 0)
    <button type="button" class="btn btn-info btn-sm view-handover-sales-btn mt-2 mt-md-0" data-sales='@json($allDaySales)' data-title="Shift #{{ $dayClosing->shift?->id ?? '—' }} — {{ $dayClosing->user->name ?? 'Staff' }}">
      <i class="fa fa-eye"></i> View {{ count($allDaySales) }} sale(s)
    </button>
    @endif
  </h5>
  <div class="table-responsive mb-3">
    <table class="table table-bordered table-sm mb-0">
      <thead class="thead-light">
        <tr>
          <th>Orders</th>
          <th>Gross Sales</th>
          <th>Collected on Orders</th>
          <th class="text-danger">Still Unpaid</th>
          <th class="text-success">Handed Over</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ $shiftStats['orders'] }}</td>
          <td><strong>{{ money($shiftGross) }}</strong></td>
          <td>{{ money($shiftCollected) }}</td>
          <td class="{{ $shiftUnpaid > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $shiftUnpaid > 0 ? money($shiftUnpaid) : '—' }}</td>
          <td class="text-success font-weight-bold" rowspan="{{ $priorShiftOrders > 0 ? 2 : 1 }}">{{ money($shiftStats['handover']) }}</td>
        </tr>
        @if($priorShiftOrders > 0)
        <tr class="table-light">
          <td><span class="badge badge-warning">Prior shift</span> {{ $priorShiftOrders }}</td>
          <td><strong>{{ money($priorShiftCollected) }}</strong></td>
          <td>{{ money($priorShiftCollected) }}</td>
          <td class="text-muted">—</td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
  @if($shiftUnpaid > 0)
  <p class="text-muted small mb-4">
    <i class="fa fa-info-circle"></i>
    Staff gave you <strong>{{ money($shiftStats['handover']) }}</strong> now.
    <strong>{{ money($shiftUnpaid) }}</strong> is still owed by customers on credit from this shift.
  </p>
  @endif

  @if($priorShiftOrders > 0 && $shiftStats['orders'] === 0)
  <p class="text-muted small mb-3">
    <i class="fa fa-info-circle"></i>
    No new orders this shift — handover is from collecting {{ $priorShiftOrders === 1 ? 'a prior-shift order' : $priorShiftOrders . ' prior-shift orders' }}.
  </p>
  @endif

  @if(($debtCollections['count'] ?? 0) > 0)
  <h5 class="mt-2"><i class="fa fa-history"></i> Prior-Shift Collections ({{ $debtCollections['count'] }})</h5>
  <div class="d-lg-none mb-3">
    @foreach($debtCollections['items'] as $item)
    <div class="dc-mobile-card">
      <div class="dc-mobile-head">
        <div>
          <div class="dc-mobile-title">{{ $item['customer'] }}</div>
          <div class="dc-mobile-meta">{{ $item['collected_at'] }}</div>
        </div>
        <strong class="text-success">{{ money($item['amount']) }}</strong>
      </div>
      <div class="dc-mobile-meta">{{ $item['sale_ref'] }} · {{ ucfirst(str_replace('_', ' ', $item['method'])) }}</div>
    </div>
    @endforeach
  </div>
  <div class="table-responsive mb-4 d-none d-lg-block">
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
    <div class="col-12 col-md-6 mb-3 mb-md-0">
      <h5>How Staff Paid You</h5>
      <table class="table table-bordered table-sm mb-0">
        @forelse($submittedBreakdown as $key => $amount)
          @if($amount != 0)
          <tr>
            <th>{{ $platformBreakdown[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)) }}</th>
            <td class="text-right">{{ money($amount) }}</td>
          </tr>
          @endif
        @empty
          <tr><td colspan="2" class="text-muted">No payment breakdown recorded.</td></tr>
        @endforelse
        @if($dayClosing->total_expenses > 0)
          <tr>
            <th class="text-danger">Shift expenses</th>
            <td class="text-right text-danger">− {{ money($dayClosing->total_expenses) }}</td>
          </tr>
        @endif
      </table>
    </div>
    <div class="col-12 col-md-6">
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
    <h5 class="mb-2"><i class="fa fa-calculator"></i> Posts to Master Sheet After Verify</h5>
    <div class="row text-center">
      <div class="col-4 mb-2">
        <div class="small text-muted text-uppercase font-weight-bold">Customer credit</div>
        <div class="h5 mb-0 {{ $shiftUnpaid > 0 ? 'text-danger' : 'text-success' }}">{{ money($shiftUnpaid) }}</div>
        <div class="small text-muted">Still owed from this shift</div>
      </div>
      <div class="col-4 mb-2">
        <div class="small text-muted text-uppercase font-weight-bold">Profit</div>
        <div class="h5 mb-0 text-success">{{ money($financeData['net_profit'] ?? 0) }}</div>
      </div>
      <div class="col-4 mb-2">
        <div class="small text-muted text-uppercase font-weight-bold">Circulation</div>
        <div class="h5 mb-0 text-primary">{{ money($financeData['closing_circulation'] ?? 0) }}</div>
        <div class="small text-muted">Capital from this handover</div>
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
    @php $canVerifyNow = $canVerifyNow ?? true; @endphp
    <div class="mt-4 border-top pt-3 {{ $canVerifyNow ? '' : 'opacity-75' }}">
      <h5 class="mb-3"><i class="fa fa-check-square-o"></i> Verify Handover</h5>
      <form action="{{ route('day-closing.verify', $dayClosing) }}" method="POST" class="verify-handover-form" data-closing-id="{{ $dayClosing->id }}">
        @csrf
        <div class="row">
          <div class="col-12 col-md-4 mb-3">
            <label class="font-weight-bold text-muted small text-uppercase">Staff handed over</label>
            <div class="form-control bg-light font-weight-bold">{{ money($handoverSummary['final_handover'] ?? $dayClosing->net_amount) }}</div>
          </div>
          <div class="col-12 col-md-4 mb-3">
            <label for="actual-received-{{ $dayClosing->id }}" class="font-weight-bold">Actual Amount Received <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">TZS</span></div>
              <input type="number"
                     id="actual-received-{{ $dayClosing->id }}"
                     name="actual_received"
                     class="form-control actual-received-input font-weight-bold"
                     min="0"
                     step="1"
                     {{ $canVerifyNow ? 'required' : 'disabled' }}
                     value="{{ old('actual_received', round($handoverSummary['final_handover'] ?? $dayClosing->net_amount)) }}"
                     data-expected="{{ round($handoverSummary['final_handover'] ?? $dayClosing->net_amount) }}">
            </div>
          </div>
          <div class="col-12 col-md-4 mb-3">
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
                    {{ $canVerifyNow ? '' : 'disabled' }}
                    placeholder="Explain why the staff handed over less than expected...">{{ old('shortage_note') }}</textarea>
        </div>
        <div class="d-flex flex-wrap align-items-center">
          <button type="submit" class="btn btn-success btn-lg mb-2" {{ $canVerifyNow ? '' : 'disabled' }}>
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
