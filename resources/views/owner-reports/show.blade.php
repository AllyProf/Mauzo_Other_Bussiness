@extends('layouts.app')

@section('title', __('owner_reports.show.title'))

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
    <h1><i class="fa fa-line-chart"></i> {{ __('owner_reports.show.title') }}</h1>
    <p>{{ $displayDate }}</p>
  </div>
  <div>
    <a href="{{ route('owner-reports.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> {{ __('owner_reports.back') }}</a>
    <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> {{ __('owner_reports.print') }}</button>
  </div>
</div>

@if($report->status === 'finalized')
  <div class="alert alert-success d-print-none"><i class="fa fa-check-circle"></i> <strong>{{ __('owner_reports.show.finalized') }}</strong> {{ __('owner_reports.show.finalized_by', ['name' => $report->finalizer->name ?? 'Owner', 'date' => $report->finalized_at?->format('M d, Y h:i A')]) }}</div>
@else
  <div class="alert alert-warning d-print-none"><i class="fa fa-clock-o"></i> {{ __('owner_reports.show.pending_review') }}</div>
@endif

<div class="row mb-3">
  <div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon fa fa-shopping-cart fa-3x"></i><div class="info"><h4>{{ __('owner_reports.show.total_sold') }}</h4><p><b>TZS {{ number_format($data['gross_sales'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small info coloured-icon"><i class="icon fa fa-money fa-3x"></i><div class="info"><h4>{{ __('owner_reports.show.collected') }}</h4><p><b>TZS {{ number_format($data['total_collected'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small warning coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i><div class="info"><h4>{{ __('owner_reports.show.debt') }}</h4><p><b>TZS {{ number_format($data['outstanding_debt'], 0) }}</b></p></div></div></div>
  <div class="col-md-3"><div class="widget-small success coloured-icon"><i class="icon fa fa-line-chart fa-3x"></i><div class="info"><h4>{{ __('owner_reports.show.net_profit') }}</h4><p><b>TZS {{ number_format($data['net_profit'], 0) }}</b></p></div></div></div>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="tile report-card">
      <h3 class="tile-title">{{ __('owner_reports.show.sales_collections') }}</h3>
      <div class="tile-body">
        <table class="table table-bordered table-sm mb-4">
          <tr><th width="40%">{{ __('owner_reports.show.gross_sales') }}</th><td>TZS {{ number_format($data['gross_sales'], 2) }}</td></tr>
          <tr><th>{{ __('owner_reports.show.cogs') }}</th><td>TZS {{ number_format($data['cost_of_goods'], 2) }}</td></tr>
          <tr class="table-light"><th>{{ __('owner_reports.show.gross_profit') }}</th><td><strong>TZS {{ number_format($data['gross_profit'], 2) }}</strong></td></tr>
          <tr><th>{{ __('owner_reports.show.credit_debt') }}</th><td class="text-danger">TZS {{ number_format($data['outstanding_debt'], 2) }}</td></tr>
          <tr><th>{{ __('owner_reports.show.total_collected') }}</th><td class="text-success"><strong>TZS {{ number_format($data['total_collected'], 2) }}</strong></td></tr>
        </table>

        <h5>{{ __('owner_reports.show.collections_by_platform') }}</h5>
        <table class="table table-bordered table-sm platform-row">
          <thead><tr><th>{{ __('tables.columns.platform') }}</th><th>{{ __('tables.columns.amount') }}</th></tr></thead>
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
              <tr><td colspan="2" class="text-muted text-center">{{ __('owner_reports.empty.collections') }}</td></tr>
            @endforelse
          </tbody>
        </table>

        <h5 class="mt-4">{{ __('owner_reports.show.staff_reconciliation') }}</h5>
        <p class="small text-muted mb-2">{{ __('owner_reports.show.submitted_on', ['name' => $dayClosing->user->name, 'date' => $dayClosing->submitted_at?->format('M d, Y h:i A')]) }}</p>
        @if($dayClosing->report_notes)
          <div class="p-2 bg-light rounded small mb-2"><strong>{{ __('owner_reports.show.staff_note') }}</strong> {{ $dayClosing->report_notes }}</div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-refresh"></i> {{ __('owner_reports.show.circulation_profit') }}</h3>
      <div class="tile-body">
        <table class="table table-bordered table-sm">
          <tr><th>{{ __('owner_reports.show.opening_circulation') }}</th><td>TZS {{ number_format($data['opening_circulation'], 2) }}</td></tr>
          <tr><th>{{ __('owner_reports.show.collections_today') }}</th><td class="text-success">TZS {{ number_format($data['total_collected'], 2) }}</td></tr>
          @if($data['expense_deduct_from'] === 'circulation')
            <tr><th>{{ __('owner_reports.show.staff_expenses') }}</th><td class="text-danger">TZS {{ number_format($data['staff_expenses'], 2) }}</td></tr>
            <tr><th>{{ __('owner_reports.show.owner_expenses') }}</th><td class="text-danger">TZS {{ number_format($data['owner_expenses'], 2) }}</td></tr>
          @else
            <tr><td colspan="2" class="small text-muted">{{ __('owner_reports.show.expenses_from_profit', ['amount' => number_format($data['total_expenses'], 2)]) }}</td></tr>
          @endif
          <tr class="table-primary">
            <th>{{ __('owner_reports.show.closing_circulation') }}</th>
            <td><strong>TZS {{ number_format($data['closing_circulation'], 2) }}</strong></td>
          </tr>
          <tr class="table-success">
            <th>{{ __('owner_reports.show.net_profit_today') }}</th>
            <td><strong>TZS {{ number_format($data['net_profit'], 2) }}</strong></td>
          </tr>
          <tr><th>{{ __('owner_reports.show.opening_profit') }}</th><td>TZS {{ number_format($data['opening_profit'] ?? 0, 2) }}</td></tr>
          <tr class="table-success">
            <th>{{ __('owner_reports.show.total_profit_rollover') }}</th>
            <td><strong>TZS {{ number_format($data['closing_profit'] ?? 0, 2) }}</strong></td>
          </tr>
        </table>
        <small class="text-muted d-block mt-2">
          {{ __('owner_reports.show.expenses_deduct_note', ['source' => __('owner_reports.labels.'.$data['expense_deduct_from'])]) }}
        </small>
      </div>
    </div>

    @if(Auth::user()->role === 'owner' && $report->status !== 'finalized')
    <div class="tile d-print-none">
      <h3 class="tile-title">{{ __('owner_reports.show.petty_cash_finalize') }}</h3>
      <div class="tile-body">
        <p class="small text-muted">{{ __('owner_reports.show.petty_cash_hint') }}</p>
        <a href="{{ route('petty-cash.index', ['date' => $parsedDate]) }}" class="btn btn-outline-primary btn-block mb-3">
          <i class="fa fa-money"></i> {{ __('owner_reports.show.manage_petty_cash') }}
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
            <label>{{ __('owner_reports.show.owner_notes_optional') }}</label>
            <textarea name="owner_notes" class="form-control" rows="2" placeholder="{{ __('owner_reports.show.owner_notes_placeholder') }}">{{ old('owner_notes', $report->owner_notes) }}</textarea>
          </div>
          <button type="button" class="btn btn-success btn-block" id="finalizeBtn">
            <i class="fa fa-check"></i> {{ __('owner_reports.show.finalize_button') }}
          </button>
        </form>
      </div>
    </div>
    @endif

    @if($report->owner_notes && $report->status === 'finalized')
      <div class="tile">
        <h3 class="tile-title">{{ __('owner_reports.show.owner_notes') }}</h3>
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
      title: @json(__('owner_reports.show.finalize_confirm_title')),
      text: @json(__('owner_reports.show.finalize_confirm_text', [
        'circulation' => number_format($data['closing_circulation'], 0),
        'profit' => number_format($data['closing_profit'] ?? 0, 0),
      ])),
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: @json(__('owner_reports.show.yes_finalize'))
    }).then(r => { if (r.isConfirmed) $('#finalizeForm').submit(); });
  });
});
</script>
@endif
@endsection
