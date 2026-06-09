@extends('layouts.app')

@section('title', 'Petty Cash')

@section('styles')
<style>
  .balance-card { border-left: 4px solid #940000; }
  .balance-card.profit { border-left-color: #28a745; }
  .fund-badge-circulation { background: #e3f2fd; color: #1565c0; }
  .fund-badge-profit { background: #e8f5e9; color: #2e7d32; }
  .balance-date-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.04em; }
  .balance-preview {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-left: 4px solid #940000;
    border-radius: 4px;
    padding: 12px 14px;
  }
  .balance-preview.is-finalized { border-left-color: #ffc107; background: #fffdf5; }
  .fund-option-card {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px 12px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
  }
  .fund-option-card.active {
    border-color: #940000;
    background: #fff5f5;
  }
  .fund-option-card .available-amount {
    font-family: 'Courier New', Courier, monospace;
    font-weight: 700;
  }
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
  .issue-form-disabled { opacity: 0.65; pointer-events: none; }
  .btn.is-loading { pointer-events: none; }
  .petty-cash-page .widget-small { min-height: 90px; border-radius: 8px !important; margin-bottom: 15px; }
  .petty-cash-page .widget-small .icon { min-width: 70px !important; padding: 10px !important; font-size: 2rem !important; }
  .petty-cash-page .widget-small .info h4 { font-size: 0.85rem !important; }
  .petty-cash-page .widget-small .info p { font-size: 15px !important; word-break: break-word; }
  .petty-cash-page .pc-filter-form .form-group { margin-bottom: 0.75rem; }
  .petty-cash-page .pc-mobile-card {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
  }
  .petty-cash-page .pc-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
  .petty-cash-page .pc-mobile-date { font-weight: 700; color: #940000; }
  .petty-cash-page .pc-mobile-meta { font-size: 0.82rem; color: #6c757d; margin-top: 2px; }
  .petty-cash-page .pc-mobile-desc { font-size: 0.9rem; margin-bottom: 10px; line-height: 1.4; word-break: break-word; }
  .petty-cash-page .pc-mobile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 12px; margin-bottom: 10px; }
  .petty-cash-page .pc-mobile-stat span { display: block; font-size: 0.72rem; text-transform: uppercase; color: #6c757d; font-weight: 600; letter-spacing: 0.02em; }
  .petty-cash-page .pc-mobile-stat strong { display: block; font-size: 0.88rem; margin-top: 2px; word-break: break-word; }
  .petty-cash-page .pc-mobile-actions { display: flex; align-items: center; padding-top: 8px; border-top: 1px solid #eee; }

  @media (max-width: 991.98px) {
    .petty-cash-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .petty-cash-page .app-title p { font-size: 0.88rem; }
    .petty-cash-page .business-type-tabs { padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
  }

  @media (max-width: 767.98px) {
    .petty-cash-page .app-title h1 { font-size: 1.15rem; }
    .petty-cash-page .app-title p { font-size: 0.82rem; }
    .petty-cash-page .widget-small .icon { min-width: 58px !important; font-size: 1.6rem !important; }
    .petty-cash-page .widget-small .info p { font-size: 14px !important; }
    .petty-cash-page .fund-option-card .d-flex { flex-direction: column; align-items: flex-start !important; gap: 4px; }
    .petty-cash-page .fund-option-card .available-amount { align-self: flex-end; }
  }
</style>
@endsection

@section('content')
<div class="petty-cash-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Petty Cash</h1>
    <p>Issue cash for restock, payments, or salaries — choose profit or circulation, and see available balances before you issue.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Petty Cash</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2">Business:</span>
    <div class="business-type-tabs mb-2">
      <a href="{{ route('petty-cash.index', request()->except('business_type')) }}"
         class="business-type-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> All
      </a>
      @foreach($businessTypes as $type)
        <a href="{{ route('petty-cash.index', array_merge(request()->except('business_type'), ['business_type' => $type['key']])) }}"
           class="business-type-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
          <i class="fa {{ $type['icon'] ?? 'fa-store' }}"></i> {{ $type['label'] }}
        </a>
      @endforeach
    </div>
  </div>
  @if($activeBusinessType ?? false)
    <p class="small text-muted mb-0">Balances and history show <strong>{{ $balances['business_type_label'] ?? $activeBusinessType }}</strong> only — from today&apos;s sales for this business.</p>
  @else
    <p class="small text-muted mb-0">Select a business tab or choose one when issuing petty cash so amounts deduct from the correct department.</p>
  @endif
</div>
@endif

<div class="row mb-3">
  <div class="col-12 col-md-6">
    <div class="widget-small primary coloured-icon balance-card">
      <i class="icon fa fa-refresh fa-3x"></i>
      <div class="info">
        <div class="balance-date-label">Available for <span id="balance-date-label-circulation">{{ \Carbon\Carbon::parse($selectedDate)->format('d M, Y') }}</span></div>
        <h4>Money in Circulation</h4>
        <p><strong id="available-circulation">TZS {{ number_format($balances['available_circulation'], 0) }}</strong></p>
        <small class="text-muted" id="circulation-meta">
          Opening: TZS {{ number_format($balances['opening_circulation'], 0) }}
          @if($balances['owner_circulation_spent'] > 0)
            · Issued: TZS {{ number_format($balances['owner_circulation_spent'], 0) }}
          @endif
        </small>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="widget-small info coloured-icon balance-card profit">
      <i class="icon fa fa-line-chart fa-3x"></i>
      <div class="info">
        <div class="balance-date-label">Available for <span id="balance-date-label-profit">{{ \Carbon\Carbon::parse($selectedDate)->format('d M, Y') }}</span></div>
        <h4>Profit Available</h4>
        <p><strong id="available-profit">TZS {{ number_format($balances['available_profit'], 0) }}</strong></p>
        <small class="text-muted" id="profit-meta">
          Opening profit: TZS {{ number_format($balances['opening_profit'], 0) }}
          · Day net: TZS {{ number_format($balances['daily_net_profit'], 0) }}
          @if($balances['owner_profit_spent'] > 0)
            · Issued: TZS {{ number_format($balances['owner_profit_spent'], 0) }}
          @endif
        </small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12 col-lg-5 mb-3 mb-lg-0">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-plus-circle"></i> Issue Petty Cash</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('petty-cash.store') }}" id="issuePettyCashForm">
          @csrf

          <div class="form-group">
            <label class="control-label font-weight-bold">Issue Date</label>
            <input type="date" name="expense_date" id="expense_date" class="form-control" value="{{ old('expense_date', $selectedDate) }}" required>
            <small class="form-text text-muted">Balances below update automatically when you change the date.</small>
          </div>

          <div class="balance-preview mb-3 {{ $balances['is_finalized'] ? 'is-finalized' : '' }}" id="balancePreview">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <strong><i class="fa fa-info-circle"></i> Balances on <span id="preview-date-label">{{ \Carbon\Carbon::parse($selectedDate)->format('d M, Y') }}</span></strong>
              <span class="badge badge-{{ $balances['is_finalized'] ? 'warning' : 'success' }}" id="preview-status-badge">
                {{ $balances['is_finalized'] ? 'Finalized' : 'Open' }}
              </span>
            </div>
            <div class="row small">
              <div class="col-6">
                <span class="text-muted d-block">Circulation</span>
                <span class="font-weight-bold text-primary" id="preview-circulation">TZS {{ number_format($balances['available_circulation'], 0) }}</span>
              </div>
              <div class="col-6">
                <span class="text-muted d-block">Profit</span>
                <span class="font-weight-bold text-success" id="preview-profit">TZS {{ number_format($balances['available_profit'], 0) }}</span>
              </div>
            </div>
            <div class="alert alert-warning py-2 px-2 mt-2 mb-0 small {{ $balances['is_finalized'] ? '' : 'd-none' }}" id="finalizedNotice">
              <i class="fa fa-lock"></i> This date is finalized. Choose another date to issue petty cash.
            </div>
          </div>

          <div id="issueFormFields" class="{{ $balances['is_finalized'] ? 'issue-form-disabled' : '' }}">
            @if($multiBusiness ?? false)
            <div class="form-group">
              <label class="control-label font-weight-bold">Business / Department</label>
              <select name="business_type_key" id="business_type_key" class="form-control" required>
                <option value="">— Select business —</option>
                @foreach($businessTypes as $type)
                  <option value="{{ $type['key'] }}" {{ old('business_type_key', $activeBusinessType) === $type['key'] ? 'selected' : '' }}>
                    {{ $type['label'] }}
                  </option>
                @endforeach
              </select>
              <small class="form-text text-muted">Issue is deducted from this business type&apos;s circulation or profit.</small>
            </div>
            @endif

            <div class="form-group">
              <label class="control-label font-weight-bold">Amount (TZS)</label>
              <input type="number" name="amount" id="issue_amount" class="form-control" min="0.01" step="0.01" max="{{ old('fund_source', 'circulation') === 'profit' ? $balances['available_profit'] : $balances['available_circulation'] }}" value="{{ old('amount') }}" required>
              <small class="form-text text-muted">Maximum for selected source: <strong id="amount-max-label">TZS {{ number_format(old('fund_source', 'circulation') === 'profit' ? $balances['available_profit'] : $balances['available_circulation'], 0) }}</strong></small>
              <div class="invalid-feedback d-block d-none" id="amount-error">Amount exceeds available balance for the selected source.</div>
            </div>

            <div class="form-group">
              <label class="control-label font-weight-bold">Purpose</label>
              <select name="category" class="form-control" required>
                @foreach(\App\Models\BusinessOwnerExpense::CATEGORIES as $value => $label)
                  <option value="{{ $value }}" {{ old('category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="form-group">
              <label class="control-label font-weight-bold">Issue To Staff <span class="text-muted font-weight-normal">(optional)</span></label>
              <select name="issued_to_user_id" class="form-control">
                <option value="">— Not linked to a staff member —</option>
                @foreach($staffMembers as $member)
                  <option value="{{ $member->id }}" {{ (string) old('issued_to_user_id') === (string) $member->id ? 'selected' : '' }}>
                    {{ $member->name }}
                  </option>
                @endforeach
              </select>
              <small class="form-text text-muted">Useful when issuing salary, float, or staff-specific payments.</small>
            </div>

            <div class="form-group">
              <label class="control-label font-weight-bold">Description</label>
              <textarea name="description" class="form-control" rows="4" maxlength="1000" placeholder="Describe what this petty cash is for — supplier, items, reason for payment, etc." required>{{ old('description') }}</textarea>
            </div>

            <div class="form-group mb-3">
              <label class="control-label font-weight-bold d-block mb-2">Issue From</label>
              <input type="hidden" name="fund_source" id="fund_source" value="{{ old('fund_source', 'circulation') }}">

              <div class="fund-option-card mb-2 {{ old('fund_source', 'circulation') === 'circulation' ? 'active' : '' }}" data-fund="circulation">
                <div class="custom-control custom-radio">
                  <input type="radio" id="fund_circulation" class="custom-control-input fund-source-radio" value="circulation" {{ old('fund_source', 'circulation') === 'circulation' ? 'checked' : '' }}>
                  <label class="custom-control-label w-100" for="fund_circulation">
                    <strong>Money in Circulation</strong>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                      <small class="text-muted">Working capital for restock &amp; operations</small>
                      <span class="available-amount text-primary" id="fund-circulation-amount">TZS {{ number_format($balances['available_circulation'], 0) }}</span>
                    </div>
                  </label>
                </div>
              </div>

              <div class="fund-option-card {{ old('fund_source') === 'profit' ? 'active' : '' }}" data-fund="profit">
                <div class="custom-control custom-radio">
                  <input type="radio" id="fund_profit" class="custom-control-input fund-source-radio" value="profit" {{ old('fund_source') === 'profit' ? 'checked' : '' }}>
                  <label class="custom-control-label w-100" for="fund_profit">
                    <strong>Profit</strong>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                      <small class="text-muted">Deduct from profit rollover</small>
                      <span class="available-amount text-success" id="fund-profit-amount">TZS {{ number_format($balances['available_profit'], 0) }}</span>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="issueSubmitBtn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-check"></i> Issue Petty Cash
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-list"></i> Petty Cash History</h3>
      <div class="tile-body">
        <form method="GET" action="{{ route('petty-cash.index') }}" class="row mb-3 pc-filter-form" id="historyFilterForm">
          <input type="hidden" name="date" value="{{ $selectedDate }}">
          @if($activeBusinessType ?? false)
            <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
          @endif
          <div class="col-12 col-sm-6 col-md-3 form-group">
            <label class="small font-weight-bold mb-1">From</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
          </div>
          <div class="col-12 col-sm-6 col-md-3 form-group">
            <label class="small font-weight-bold mb-1">To</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
          </div>
          <div class="col-12 col-sm-6 col-md-3 form-group">
            <label class="small font-weight-bold mb-1">Source</label>
            <select name="fund_source" class="form-control form-control-sm">
              <option value="">All sources</option>
              <option value="circulation" {{ request('fund_source') === 'circulation' ? 'selected' : '' }}>Circulation</option>
              <option value="profit" {{ request('fund_source') === 'profit' ? 'selected' : '' }}>Profit</option>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-md-3 form-group d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary btn-block" id="filterHistoryBtn"><i class="fa fa-filter"></i> Filter</button>
          </div>
        </form>

        <div class="d-lg-none mb-3">
          @include('petty-cash.partials.expense-mobile-list', [
            'expenses' => $expenses,
            'business' => $business,
            'multiBusiness' => $multiBusiness ?? false,
            'activeBusinessType' => $activeBusinessType ?? false,
          ])
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table table-hover table-bordered table-sm">
            <thead class="thead-dark">
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                @if($multiBusiness ?? false)
                <th>{{ __('tables.columns.business') }}</th>
                @endif
                <th>{{ __('tables.columns.description') }}</th>
                <th>Purpose</th>
                <th>Issued To</th>
                <th>Source</th>
                <th class="text-right">Amount</th>
                <th>Recorded By</th>
                <th class="text-center" style="width:60px;">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($expenses as $expense)
                @php $isLocked = $expense->report && $expense->report->status === 'finalized'; @endphp
                <tr>
                  <td nowrap>{{ $expense->expense_date->format('d M, Y') }}</td>
                  @if($multiBusiness ?? false)
                  <td nowrap>{{ $expense->business_type_key ? $expense->businessTypeLabel($business) : '—' }}</td>
                  @endif
                  <td style="max-width:220px;">{{ Str::limit($expense->description, 80) }}</td>
                  <td><span class="badge badge-light border">{{ $expense->categoryLabel() }}</span></td>
                  <td>{{ $expense->issuedTo->name ?? '—' }}</td>
                  <td>
                    <span class="badge fund-badge-{{ $expense->fund_source ?? 'circulation' }}">
                      {{ $expense->fundSourceLabel() }}
                    </span>
                  </td>
                  <td class="text-right font-weight-bold text-danger" nowrap>TZS {{ number_format($expense->amount, 0) }}</td>
                  <td>{{ $expense->recorder->name ?? '—' }}</td>
                  <td class="text-center">
                    @if(! $isLocked)
                      <form action="{{ route('petty-cash.destroy', $expense) }}" method="POST" class="delete-petty-cash-form">
                        @csrf @method('DELETE')
                        <button type="button" class="btn btn-xs btn-danger delete-petty-cash-btn" title="Remove"><i class="fa fa-trash"></i></button>
                      </form>
                    @else
                      <span class="text-muted" title="Finalized"><i class="fa fa-lock"></i></span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="{{ ($multiBusiness ?? false) ? 9 : 8 }}" class="text-center py-4 text-muted">No petty cash issued yet{{ ($activeBusinessType ?? false) ? ' for this business' : '' }}.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
          {{ $expenses->links() }}
        </div>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  const balancesUrl = @json(route('petty-cash.balances'));
  const activeBusinessType = @json($activeBusinessType ?? null);
  let currentBalances = {
    available_circulation: {{ $balances['available_circulation'] }},
    available_profit: {{ $balances['available_profit'] }},
    is_finalized: {{ $balances['is_finalized'] ? 'true' : 'false' }}
  };

  function formatMoney(value) {
    return 'TZS ' + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
  }

  function setButtonLoading($btn, isLoading, loadingText) {
    if (!$btn.length) return;

    if (isLoading) {
      if (!$btn.data('original-html')) {
        $btn.data('original-html', $btn.html());
      }
      $btn.prop('disabled', true).addClass('is-loading');
      if (loadingText === false) {
        $btn.html('<i class="fa fa-spinner fa-spin"></i>');
      } else {
        $btn.html('<i class="fa fa-spinner fa-spin mr-1"></i> ' + (loadingText || 'Loading...'));
      }
    } else {
      $btn.prop('disabled', false).removeClass('is-loading');
      if ($btn.data('original-html')) {
        $btn.html($btn.data('original-html'));
      }
    }
  }

  function selectedFundSource() {
    return $('input.fund-source-radio:checked').val() || 'circulation';
  }

  function maxForSelectedSource() {
    return selectedFundSource() === 'profit'
      ? currentBalances.available_profit
      : currentBalances.available_circulation;
  }

  function updateAmountConstraints() {
    const max = maxForSelectedSource();
    const $amount = $('#issue_amount');
    $amount.attr('max', max);
    $('#amount-max-label').text(formatMoney(max));
    $('#fund-circulation-amount').text(formatMoney(currentBalances.available_circulation));
    $('#fund-profit-amount').text(formatMoney(currentBalances.available_profit));

    const amount = parseFloat($amount.val());
    if (amount && amount > max) {
      $('#amount-error').removeClass('d-none');
      $('#issueSubmitBtn').prop('disabled', true);
    } else {
      $('#amount-error').addClass('d-none');
      $('#issueSubmitBtn').prop('disabled', currentBalances.is_finalized);
    }
  }

  function applyBalances(data) {
    currentBalances = data;
    const circulationMeta = 'Opening: ' + formatMoney(data.opening_circulation)
      + (data.owner_circulation_spent > 0 ? ' · Issued: ' + formatMoney(data.owner_circulation_spent) : '');
    const profitMeta = 'Opening profit: ' + formatMoney(data.opening_profit)
      + ' · Day net: ' + formatMoney(data.daily_net_profit)
      + (data.owner_profit_spent > 0 ? ' · Issued: ' + formatMoney(data.owner_profit_spent) : '');

    $('#balance-date-label-circulation, #balance-date-label-profit, #preview-date-label').text(data.date_label);
    $('#available-circulation, #preview-circulation').text(formatMoney(data.available_circulation));
    $('#available-profit, #preview-profit').text(formatMoney(data.available_profit));
    $('#circulation-meta').text(circulationMeta);
    $('#profit-meta').text(profitMeta);

    const $preview = $('#balancePreview');
    if (data.is_finalized) {
      $preview.addClass('is-finalized');
      $('#preview-status-badge').removeClass('badge-success').addClass('badge-warning').text('Finalized');
      $('#finalizedNotice').removeClass('d-none');
      $('#issueFormFields').addClass('issue-form-disabled');
      $('#issueSubmitBtn').prop('disabled', true);
    } else {
      $preview.removeClass('is-finalized');
      $('#preview-status-badge').removeClass('badge-warning').addClass('badge-success').text('Open');
      $('#finalizedNotice').addClass('d-none');
      $('#issueFormFields').removeClass('issue-form-disabled');
      $('#issueSubmitBtn').prop('disabled', false);
    }

    updateAmountConstraints();
  }

  function fetchBalances(date) {
    const $dateInput = $('#expense_date');
    $dateInput.prop('disabled', true);
    $('#balancePreview').css('opacity', '0.6');

    const businessType = $('#business_type_key').val() || activeBusinessType || null;
    const params = { date: date };
    if (businessType) {
      params.business_type = businessType;
    }

    $.get(balancesUrl, params)
      .done(applyBalances)
      .fail(function() {
        Swal.fire('Error', 'Could not load balances for the selected date.', 'error');
      })
      .always(function() {
        $dateInput.prop('disabled', false);
        $('#balancePreview').css('opacity', '1');
      });
  }

  $('#expense_date').on('change', function() {
    fetchBalances($(this).val());
  });

  $('#business_type_key').on('change', function() {
    fetchBalances($('#expense_date').val());
  });

  $('input.fund-source-radio').on('change', function() {
    const value = $(this).val();
    $('#fund_source').val(value);
    $('.fund-option-card').removeClass('active');
    $('.fund-option-card[data-fund="' + value + '"]').addClass('active');
    updateAmountConstraints();
  });

  $('.fund-option-card').on('click', function(e) {
    if ($(e.target).is('input, label')) return;
    $(this).find('input.fund-source-radio').prop('checked', true).trigger('change');
  });

  $('#issue_amount').on('input', updateAmountConstraints);

  $('#issuePettyCashForm').on('submit', function(e) {
    const amount = parseFloat($('#issue_amount').val());
    const max = maxForSelectedSource();
    if (currentBalances.is_finalized) {
      e.preventDefault();
      Swal.fire('Day Finalized', 'You cannot issue petty cash on a finalized date.', 'warning');
      return;
    }
    if (!amount || amount <= 0) {
      e.preventDefault();
      Swal.fire('Invalid Amount', 'Enter a valid amount greater than zero.', 'warning');
      return;
    }
    if (amount > max) {
      e.preventDefault();
      Swal.fire('Insufficient Balance', 'Amount exceeds available ' + (selectedFundSource() === 'profit' ? 'profit' : 'circulation') + ' (' + formatMoney(max) + ').', 'warning');
      return;
    }

    setButtonLoading($('#issueSubmitBtn'), true, 'Issuing...');
  });

  $('#historyFilterForm').on('submit', function() {
    setButtonLoading($('#filterHistoryBtn'), true, 'Filtering...');
  });

  $('.delete-petty-cash-btn').on('click', function() {
    const form = $(this).closest('form');
    const $btn = $(this);
    Swal.fire({
      title: 'Remove this petty cash entry?',
      text: 'This will restore the issued amount to the selected fund.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Yes, remove it'
    }).then((result) => {
      if (result.isConfirmed) {
        setButtonLoading($btn, true, false);
        form.submit();
      }
    });
  });

  updateAmountConstraints();
});
</script>
@endsection
