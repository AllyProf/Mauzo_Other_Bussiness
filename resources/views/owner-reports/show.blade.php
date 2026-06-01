@extends('layouts.app')

@section('title', 'Daily Business Report')

@section('styles')
<style>
  .report-card { border-left: 4px solid #940000; }
  .platform-row td { font-size: 0.95rem; }
  @media print { .app-title, .d-print-none, .app-sidebar, .app-header { display: none !important; } .app-content { margin-left: 0 !important; } }
</style>
@endsection

@section('content')
<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-line-chart"></i> Daily Business Report</h1>
    <p>{{ $displayDate }}</p>
  </div>
  <div>
    <a href="{{ route('owner-reports.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
  </div>
</div>

@if($report->status === 'finalized')
  <div class="alert alert-success d-print-none"><i class="fa fa-check-circle"></i> <strong>Finalized</strong> by {{ $report->finalizer->name ?? 'Owner' }} on {{ $report->finalized_at?->format('M d, Y h:i A') }}. Circulation carried to next day.</div>
@else
  <div class="alert alert-warning d-print-none"><i class="fa fa-clock-o"></i> Pending owner review. Finalize to carry circulation to the next opening day.</div>
@endif

<div class="row mb-3">
  <div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i><div class="info"><h4>Total Sold</h4><p><b>TZS {{ number_format($data['gross_sales'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small info coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>Collected</h4><p><b>TZS {{ number_format($data['total_collected'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small warning coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i><div class="info"><h4>Debt</h4><p><b>TZS {{ number_format($data['outstanding_debt'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small success coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i><div class="info"><h4>Net Profit</h4><p><b>TZS {{ number_format($data['net_profit'], 0) }}</b></p></div></div></div>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="tile report-card">
      <h3 class="tile-title">Sales &amp; Collections</h3>
      <div class="tile-body">
        <table class="table table-bordered table-sm mb-4">
          <tr><th width="40%">Gross Sales</th><td>TZS {{ number_format($data['gross_sales'], 2) }}</td></tr>
          <tr><th>Cost of Goods (COGS)</th><td>TZS {{ number_format($data['cost_of_goods'], 2) }}</td></tr>
          <tr class="table-light"><th>Gross Profit</th><td><strong>TZS {{ number_format($data['gross_profit'], 2) }}</strong></td></tr>
          <tr><th>Credit / Debt (unpaid today)</th><td class="text-danger">TZS {{ number_format($data['outstanding_debt'], 2) }}</td></tr>
          <tr><th>Total Collected</th><td class="text-success"><strong>TZS {{ number_format($data['total_collected'], 2) }}</strong></td></tr>
        </table>

        <h5>Collections by Platform</h5>
        <table class="table table-bordered table-sm platform-row">
          <thead><tr><th>Platform</th><th>Amount</th></tr></thead>
          <tbody>
            @forelse($data['payment_breakdown'] as $key => $platform)
              @php $amount = is_array($platform) ? ($platform['amount'] ?? 0) : $platform; @endphp
              @if($amount != 0)
              <tr>
                <td>{{ is_array($platform) ? ($platform['label'] ?? ucwords(str_replace('_', ' ', $key))) : ucwords(str_replace('_', ' ', $key)) }}</td>
                <td>TZS {{ number_format($amount, 2) }}</td>
              </tr>
              @endif
            @empty
              <tr><td colspan="2" class="text-muted text-center">No collections recorded.</td></tr>
            @endforelse
          </tbody>
        </table>

        <h5 class="mt-4">Staff Reconciliation</h5>
        <p class="small text-muted mb-2">Submitted by {{ $dayClosing->user->name }} on {{ $dayClosing->submitted_at?->format('M d, Y h:i A') }}</p>
        @if($dayClosing->report_notes)
          <div class="p-2 bg-light rounded small mb-2"><strong>Staff note:</strong> {{ $dayClosing->report_notes }}</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-refresh"></i> Circulation &amp; Profit</h3>
      <div class="tile-body">
        <table class="table table-bordered table-sm">
          <tr><th>Opening Circulation</th><td>TZS {{ number_format($data['opening_circulation'], 2) }}</td></tr>
          <tr><th>+ Collections Today</th><td class="text-success">TZS {{ number_format($data['total_collected'], 2) }}</td></tr>
          @if($data['expense_deduct_from'] === 'circulation')
            <tr><th>- Staff Expenses</th><td class="text-danger">TZS {{ number_format($data['staff_expenses'], 2) }}</td></tr>
            <tr><th>- Owner Expenses</th><td class="text-danger">TZS {{ number_format($data['owner_expenses'], 2) }}</td></tr>
          @else
            <tr><td colspan="2" class="small text-muted">Expenses (TZS {{ number_format($data['total_expenses'], 2) }}) deducted from profit, not circulation.</td></tr>
          @endif
          <tr class="table-primary">
            <th>Closing Circulation</th>
            <td><strong>TZS {{ number_format($data['closing_circulation'], 2) }}</strong></td>
          </tr>
          <tr class="table-success">
            <th>Net Profit (Today)</th>
            <td><strong>TZS {{ number_format($data['net_profit'], 2) }}</strong></td>
          </tr>
          <tr><th>Opening Profit</th><td>TZS {{ number_format($data['opening_profit'] ?? 0, 2) }}</td></tr>
          <tr class="table-success">
            <th>Total Profit Rollover</th>
            <td><strong>TZS {{ number_format($data['closing_profit'] ?? 0, 2) }}</strong></td>
          </tr>
        </table>
        <small class="text-muted d-block mt-2">
          Expenses deduct from <strong>{{ $data['expense_deduct_from'] === 'profit' ? 'Profit' : 'Circulation' }}</strong>.
          Closing circulation and profit rollover carry to the next day when finalized.
        </small>
      </div>
    </div>

    @if(Auth::user()->role === 'owner' && $report->status !== 'finalized')
    <div class="tile d-print-none">
      <h3 class="tile-title">Petty Cash &amp; Finalize</h3>
      <div class="tile-body">
        <p class="small text-muted">Issue petty cash for restock, payments, or salaries on the dedicated page. Choose whether each amount comes from profit or circulation money.</p>
        <a href="{{ route('petty-cash.index', ['date' => $parsedDate]) }}" class="btn btn-outline-primary btn-block mb-3">
          <i class="fa fa-money"></i> Manage Petty Cash
        </a>

        @php
          $ownerExpenseList = \App\Models\BusinessOwnerExpense::where('business_id', Auth::user()->business_id)->whereDate('expense_date', $parsedDate)->get();
        @endphp
        @if($ownerExpenseList->isNotEmpty())
          <ul class="list-group list-group-flush border rounded mb-3">
            @foreach($ownerExpenseList as $exp)
              <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <div>
                  <strong>{{ $exp->description }}</strong>
                  <span class="badge badge-light ml-1">{{ $exp->categoryLabel() }}</span>
                  <span class="badge badge-{{ ($exp->fund_source ?? 'circulation') === 'profit' ? 'success' : 'info' }} ml-1">{{ $exp->fundSourceLabel() }}</span>
                </div>
                <span class="text-danger font-weight-bold">TZS {{ number_format($exp->amount, 0) }}</span>
              </li>
            @endforeach
          </ul>
        @endif

        <form method="POST" action="{{ route('owner-reports.finalize', $parsedDate) }}" class="mt-3" id="finalizeForm">
          @csrf
          <div class="form-group">
            <label>Owner Notes (Optional)</label>
            <textarea name="owner_notes" class="form-control" rows="2" placeholder="Notes about this day's performance...">{{ old('owner_notes', $report->owner_notes) }}</textarea>
          </div>
          <button type="button" class="btn btn-success btn-block" id="finalizeBtn">
            <i class="fa fa-check"></i> Finalize &amp; Carry Circulation Forward
          </button>
        </form>
      </div>
    </div>
    @endif

    @if($report->owner_notes && $report->status === 'finalized')
      <div class="tile">
        <h3 class="tile-title">Owner Notes</h3>
        <div class="tile-body">{!! nl2br(e($report->owner_notes)) !!}</div>
      </div>
    @endif
  </div>
</div>
@endsection

@section('scripts')
@if(Auth::user()->role === 'owner' && $report->status !== 'finalized')
<script>
jQuery(function($) {
  $('#finalizeBtn').on('click', function() {
    Swal.fire({
      title: 'Finalize Daily Report?',
      text: 'Closing circulation TZS {{ number_format($data["closing_circulation"], 0) }} and profit rollover TZS {{ number_format($data["closing_profit"] ?? 0, 0) }} will carry to the next opening day.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Yes, finalize!'
    }).then(r => { if (r.isConfirmed) $('#finalizeForm').submit(); });
  });
});
</script>
@endif
@endsection
