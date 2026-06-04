@extends('layouts.app')

@section('title', 'Debt History - SpareParts POS')

@section('css')
<style>
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    border: 1px solid #dee2e6; background: #fff; color: #495057; border-radius: 20px;
    padding: 6px 14px; font-size: 13px; white-space: nowrap; cursor: pointer; transition: all .15s ease;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Debt History</h1>
    <p>{{ ($scopedToSelf ?? false) ? 'Your debt collections and settled accounts' : 'All customer debt payments and cleared balances' }}</p>
  </div>
  <a href="{{ route('debts.index') }}" class="btn btn-primary"><i class="fa fa-credit-card"></i> Outstanding Debts</a>
</div>

@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <div class="business-type-tabs" id="debtHistoryBusinessTabs">
      <button type="button" class="business-type-tab active" data-business-type="all">
        <i class="fa fa-th-large"></i> All
      </button>
      @foreach($businessTypes as $type)
      <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
        <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
      </button>
      @endforeach
    </div>
  </div>
</div>
@endif

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Total Collected</h4>
        <p><b id="statTotalCollected">{{ money($stats['total_collected']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-list fa-3x"></i>
      <div class="info">
        <h4>Payments Recorded</h4>
        <p><b id="statPaymentsCount">{{ number_format($stats['payments_count']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info">
        <h4>Settled Accounts</h4>
        <p><b id="statSettledAccounts">{{ number_format($stats['settled_accounts']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-exclamation-circle fa-3x"></i>
      <div class="info">
        <h4>Still Outstanding</h4>
        <p><b id="statOpenBalance">{{ money($stats['open_balance']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row mb-3" id="settledSection" style="{{ $settledAccounts->isEmpty() ? 'display:none;' : '' }}">
  <div class="col-md-12">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h3 class="tile-title mb-0">Recently Settled</h3>
        <small class="text-muted" id="settledResultCount">{{ $settledAccounts->count() }} account(s)</small>
      </div>
      <div class="tile-body">
        <table class="table table-sm table-bordered mb-0">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Reference</th>
              <th>Sale Total</th>
              <th>Payments</th>
              <th>Cleared</th>
              <th>Cashier</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="settledTableBody">
            @foreach($settledAccounts as $sale)
              @php
                $typeKeys = $sale->businessTypeKeys();
                $searchText = strtolower(implode(' ', array_filter([
                  $sale->customer_name,
                  $sale->reference_no,
                  $sale->user->name ?? '',
                  $sale->updated_at->format('M d, Y'),
                  number_format($sale->total_amount, 0),
                ])));
              @endphp
              <tr class="debt-settled-row"
                  data-search="{{ $searchText }}"
                  data-business-types="{{ implode(',', $typeKeys) }}">
                <td><strong>{{ $sale->customer_name ?: 'Customer' }}</strong></td>
                <td>{{ $sale->reference_no }}</td>
                <td>{{ money($sale->total_amount) }}</td>
                <td>{{ $sale->payments->count() }}</td>
                <td>{{ $sale->updated_at->format('M d, Y') }}</td>
                <td>{{ $sale->user->name ?? '—' }}</td>
                <td>
                  <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a>
                </td>
              </tr>
            @endforeach
            <tr id="settledNoMatchRow" style="display:none;">
              <td colspan="7" class="text-center text-muted py-3">No settled accounts match your filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">Payment Collections</h3>
      </div>
      <div class="row mb-3 px-3">
        <div class="col-lg-4 col-md-6 mb-2">
          <label class="control-label font-weight-bold small text-uppercase text-muted">Search</label>
          <div class="input-group input-group-sm">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fa fa-search"></i></span>
            </div>
            <input type="text" id="debtHistorySearch" class="form-control" placeholder="Ref, customer, provider, ref no...">
          </div>
        </div>
        <div class="col-lg-2 col-md-3 mb-2">
          <label class="control-label font-weight-bold small text-uppercase text-muted">Account</label>
          <select id="debtHistoryStatus" class="form-control form-control-sm">
            <option value="all">All Accounts</option>
            <option value="open">Open Balance</option>
            <option value="settled">Fully Paid</option>
          </select>
        </div>
        <div class="col-lg-2 col-md-3 mb-2">
          <label class="control-label font-weight-bold small text-uppercase text-muted">Method</label>
          <select id="debtHistoryMethod" class="form-control form-control-sm">
            <option value="all">All Methods</option>
            @foreach($paymentMethodLabels as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-3 mb-2">
          <label class="control-label font-weight-bold small text-uppercase text-muted">From</label>
          <input type="date" id="debtHistoryFromDate" class="form-control form-control-sm">
        </div>
        <div class="col-lg-2 col-md-3 mb-2">
          <label class="control-label font-weight-bold small text-uppercase text-muted">To</label>
          <input type="date" id="debtHistoryToDate" class="form-control form-control-sm">
        </div>
        <div class="col-lg-12 d-flex align-items-end">
          <small class="text-muted" id="paymentsResultCount">{{ $payments->count() }} payment(s)</small>
        </div>
      </div>
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Reference</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Provider / Ref</th>
              <th>Collected By</th>
              <th>Order Status</th>
              <th>Balance Left</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="paymentsTableBody">
            @foreach($payments as $payment)
              @php
                $sale = $payment->sale;
                $balance = $sale ? max(0, (float) $sale->total_amount - (float) $sale->amount_paid) : 0;
                $methodLabel = $paymentMethodLabels[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', $payment->payment_method));
                $accountStatus = ($sale && $sale->payment_status === 'paid') ? 'settled' : 'open';
                $typeKeys = $sale ? $sale->businessTypeKeys() : [];
                $searchText = strtolower(implode(' ', array_filter([
                  $sale->reference_no ?? '',
                  $sale->customer_name ?? '',
                  $sale->customer_phone ?? '',
                  $payment->payment_provider,
                  $payment->transaction_reference,
                  $methodLabel,
                  $payment->user->name ?? '',
                  $payment->created_at->format('M d, Y h:i A'),
                ])));
              @endphp
              <tr class="debt-payment-row"
                  data-search="{{ $searchText }}"
                  data-status="{{ $accountStatus }}"
                  data-method="{{ $payment->payment_method }}"
                  data-date="{{ $payment->created_at->format('Y-m-d') }}"
                  data-business-types="{{ implode(',', $typeKeys) }}"
                  data-amount="{{ (float) $payment->amount }}"
                  data-balance="{{ $balance }}"
                  data-sale-id="{{ $sale->id ?? 0 }}">
                <td>{{ $payment->created_at->format('M d, Y h:i A') }}</td>
                <td>{{ $sale->reference_no ?? '—' }}</td>
                <td><strong>{{ $sale->customer_name ?? '—' }}</strong></td>
                <td>{{ $sale->customer_phone ?? '—' }}</td>
                <td class="text-success font-weight-bold">{{ money($payment->amount) }}</td>
                <td>{{ $methodLabel }}</td>
                <td>
                  @if($payment->payment_provider || $payment->transaction_reference)
                    {{ $payment->payment_provider ?? '—' }}
                    @if($payment->transaction_reference)
                      <small class="text-muted">({{ $payment->transaction_reference }})</small>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td>{{ $payment->user->name ?? '—' }}</td>
                <td>
                  @if($sale)
                    @if($sale->payment_status === 'paid')
                      <span class="badge badge-success">Paid</span>
                    @elseif($sale->payment_status === 'partial')
                      <span class="badge badge-info">Partial</span>
                    @elseif($sale->payment_status === 'debt')
                      <span class="badge badge-danger">Debt</span>
                    @else
                      <span class="badge badge-warning">{{ ucfirst($sale->payment_status) }}</span>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td class="{{ $balance > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                  {{ $balance > 0 ? money($balance) : '—' }}
                </td>
                <td>
                  @if($sale)
                    <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-primary" title="View Receipt"><i class="fa fa-eye"></i></a>
                  @endif
                </td>
              </tr>
            @endforeach
            @if($payments->isEmpty())
              <tr id="paymentsEmptyRow">
                <td colspan="11" class="text-center py-4 text-muted">No debt payment history found.</td>
              </tr>
            @endif
            <tr id="paymentsNoMatchRow" style="display:none;">
              <td colspan="11" class="text-center py-4 text-muted">No payments match your filters.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
  var hasMultipleBusinessTypes = @json($multiBusiness ?? false);
  var activeBusinessType = 'all';
  var $paymentRows = $('.debt-payment-row');
  var $settledRows = $('.debt-settled-row');
  var $paymentsNoMatch = $('#paymentsNoMatchRow');
  var $paymentsEmpty = $('#paymentsEmptyRow');
  var $settledNoMatch = $('#settledNoMatchRow');

  function formatMoney(amount) {
    return 'TZS ' + Math.round(amount).toLocaleString('en-US');
  }

  function matchesBusinessType($row) {
    if (!hasMultipleBusinessTypes || activeBusinessType === 'all') {
      return true;
    }
    var types = String($row.data('business-types') || '').split(',').filter(Boolean);
    return types.indexOf(String(activeBusinessType)) > -1;
  }

  function rowMatchesFilters($row, term, status, method, fromDate, toDate) {
    var matchesSearch = !term || String($row.data('search')).indexOf(term) > -1;
    var matchesStatus = status === 'all' || String($row.data('status')) === status;
    var matchesMethod = method === 'all' || String($row.data('method')) === method;
    var rowDate = String($row.data('date') || '');
    var matchesFrom = !fromDate || (rowDate && rowDate >= fromDate);
    var matchesTo = !toDate || (rowDate && rowDate <= toDate);
    var matchesBusiness = matchesBusinessType($row);

    return matchesSearch && matchesStatus && matchesMethod && matchesFrom && matchesTo && matchesBusiness;
  }

  function applyDebtHistoryFilters() {
    var term = ($('#debtHistorySearch').val() || '').toLowerCase().trim();
    var status = $('#debtHistoryStatus').val() || 'all';
    var method = $('#debtHistoryMethod').val() || 'all';
    var fromDate = $('#debtHistoryFromDate').val() || '';
    var toDate = $('#debtHistoryToDate').val() || '';
    var visiblePayments = 0;
    var totalCollected = 0;
    var seenOpenSales = {};
    var openBalance = 0;

    $paymentRows.each(function () {
      var $row = $(this);
      var show = rowMatchesFilters($row, term, status, method, fromDate, toDate);
      $row.toggle(show);

      if (show) {
        visiblePayments++;
        totalCollected += parseFloat($row.data('amount')) || 0;

        var saleId = String($row.data('sale-id'));
        var balance = parseFloat($row.data('balance')) || 0;
        if (balance > 0 && saleId && !seenOpenSales[saleId]) {
          seenOpenSales[saleId] = true;
          openBalance += balance;
        }
      }
    });

    var visibleSettled = 0;
    $settledRows.each(function () {
      var $row = $(this);
      var matchesSearch = !term || String($row.data('search')).indexOf(term) > -1;
      var matchesStatus = status === 'all' || status === 'settled';
      var show = matchesSearch && matchesBusinessType($row) && matchesStatus;
      $row.toggle(show);
      if (show) {
        visibleSettled++;
      }
    });

    if ($paymentRows.length === 0) {
      $paymentsNoMatch.hide();
    } else {
      if ($paymentsEmpty.length) {
        $paymentsEmpty.hide();
      }
      $paymentsNoMatch.toggle(visiblePayments === 0);
    }

    if ($settledRows.length > 0) {
      $settledNoMatch.toggle(visibleSettled === 0);
    }

    $('#statTotalCollected').text(formatMoney(totalCollected));
    $('#statPaymentsCount').text(visiblePayments.toLocaleString('en-US'));
    $('#statSettledAccounts').text(visibleSettled.toLocaleString('en-US'));
    $('#statOpenBalance').text(formatMoney(openBalance));
    $('#paymentsResultCount').text(visiblePayments + ' payment(s)');
    $('#settledResultCount').text(visibleSettled + ' account(s)');
  }

  $('#debtHistorySearch, #debtHistoryStatus, #debtHistoryMethod, #debtHistoryFromDate, #debtHistoryToDate')
    .on('input change', applyDebtHistoryFilters);

  $('#debtHistoryBusinessTabs .business-type-tab').on('click', function () {
    activeBusinessType = $(this).data('business-type');
    $('#debtHistoryBusinessTabs .business-type-tab').removeClass('active');
    $(this).addClass('active');
    applyDebtHistoryFilters();
  });

  applyDebtHistoryFilters();
});
</script>
@endsection
