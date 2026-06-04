@extends('layouts.app')

@section('title', 'Money Shorts')

@section('styles')
<style>
  .money-short-row { transition: background 0.15s ease; }
  .money-short-row:hover { background-color: rgba(148, 0, 0, 0.04) !important; }
  .status-pill { border-radius: 50px; padding: 4px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5; text-decoration: none !important;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Money Shorts</h1>
    <p>Track handover shortages and record when staff pay back cash or when the amount is deducted from salary.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('day-closing.index') }}">Daily Reconciliation</a></li>
    <li class="breadcrumb-item active">Money Shorts</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
  <div class="alert alert-info">{{ session('info') }}</div>
@endif

@if($multiBusiness ?? false)
<div class="tile mb-3 py-2">
  <div class="d-flex align-items-center flex-wrap">
    <span class="small font-weight-bold mr-2 mb-2">Business:</span>
    <div class="business-type-tabs mb-2">
      <a href="{{ route('money-shorts.index', request()->except('business_type')) }}"
         class="business-type-tab {{ empty($activeBusinessType) ? 'active' : '' }}">
        <i class="fa fa-th-list"></i> All
      </a>
      @foreach($businessTypes as $type)
        <a href="{{ route('money-shorts.index', array_merge(request()->except('business_type'), ['business_type' => $type['key']])) }}"
           class="business-type-tab {{ ($activeBusinessType ?? '') === $type['key'] ? 'active' : '' }}">
          <i class="fa {{ $type['icon'] ?? 'fa-store' }}"></i> {{ $type['label'] }}
        </a>
      @endforeach
    </div>
  </div>
  @if($activeBusinessType ?? false)
    <p class="small text-muted mb-0">
      Showing money shorts allocated to <strong>{{ $activeBusinessLabel ?? $activeBusinessType }}</strong>
      based on that department&apos;s share of the handover sales.
    </p>
  @else
    <p class="small text-muted mb-0">Select a business tab to see shorts tied to that department only.</p>
  @endif
</div>
@endif

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-list fa-3x"></i>
      <div class="info">
        <h4>Outstanding</h4>
        <p><b>{{ number_format($stats['outstanding_count']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Balance Due</h4>
        <p><b>{{ money($stats['outstanding_total']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-check fa-3x"></i>
      <div class="info">
        <h4>Settled</h4>
        <p><b>{{ number_format($stats['settled_count']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-history fa-3x"></i>
      <div class="info">
        <h4>Total Short Recorded</h4>
        <p><b>{{ money($stats['total_short']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title mb-0">Money Short Records</h3>
        <form method="GET" action="{{ route('money-shorts.index') }}" class="form-inline">
          @if($activeBusinessType ?? false)
            <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
          @endif
          <select name="status" class="form-control form-control-sm mr-2 mb-2" onchange="this.form.submit()">
            <option value="all" {{ ($statusFilter ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
            <option value="outstanding" {{ ($statusFilter ?? '') === 'outstanding' ? 'selected' : '' }}>Outstanding</option>
            <option value="settled" {{ ($statusFilter ?? '') === 'settled' ? 'selected' : '' }}>Settled</option>
          </select>
          <input type="text" name="search" class="form-control form-control-sm mr-2 mb-2" placeholder="Search staff or note..." value="{{ request('search') }}">
          <button type="submit" class="btn btn-sm btn-primary mb-2"><i class="fa fa-search"></i></button>
        </form>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0">
            <thead class="thead-light">
              <tr>
                <th>Verified</th>
                <th>Staff</th>
                @if($multiBusiness ?? false)
                <th>Business</th>
                @endif
                <th>Shift</th>
                <th>Short Date</th>
                <th>Original Short</th>
                <th>Paid / Deducted</th>
                <th>Balance Due</th>
                <th>Status</th>
                <th>Note</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($shorts as $closing)
                @php
                  $activeSettlements = $closing->settlements->reject(fn ($s) => $s->isVoided());
                  $paid = (float) ($closing->display_paid ?? $activeSettlements->sum('amount'));
                  $status = $closing->settlement_status ?? 'pending';
                @endphp
                <tr class="money-short-row">
                  <td>{{ $closing->verified_at?->format('M d, Y') ?? '—' }}</td>
                  <td><strong>{{ $closing->user->name ?? 'Unknown' }}</strong></td>
                  @if($multiBusiness ?? false)
                  <td nowrap>
                    @if($activeBusinessType ?? false)
                      {{ $activeBusinessLabel ?? $activeBusinessType }}
                    @else
                      {{ implode(', ', $closing->business_type_labels ?? []) ?: '—' }}
                    @endif
                  </td>
                  @endif
                  <td>{{ $closing->shift ? '#'.$closing->shift->id : '—' }}</td>
                  <td>{{ $closing->closing_date->format('M d, Y') }}</td>
                  <td class="text-danger font-weight-bold">
                    {{ money($closing->display_short ?? $closing->money_short) }}
                    @if(($activeBusinessType ?? false) && (float) $closing->money_short !== (float) ($closing->display_short ?? 0))
                      <br><small class="text-muted font-weight-normal">Full handover short {{ money($closing->money_short) }}</small>
                    @endif
                    @if(($closing->short_split['profit_short'] ?? 0) > 0 || ($closing->short_split['circulation_short'] ?? 0) > 0)
                      <br><small class="text-muted font-weight-normal">
                        Profit {{ money($closing->short_split['profit_short'] ?? 0) }}
                        · Circulation {{ money($closing->short_split['circulation_short'] ?? 0) }}
                      </small>
                    @endif
                  </td>
                  <td class="text-success">{{ money($paid) }}</td>
                  <td class="{{ $closing->short_balance > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                    {{ $closing->short_balance > 0 ? money($closing->short_balance) : '—' }}
                  </td>
                  <td>
                    @if($status === 'paid')
                      <span class="status-pill badge-success">Paid</span>
                    @elseif($status === 'salary_deduction')
                      <span class="status-pill badge-info">Salary</span>
                    @elseif($status === 'partial')
                      <span class="status-pill badge-warning">Partial</span>
                    @else
                      <span class="status-pill badge-danger">Pending</span>
                    @endif
                  </td>
                  <td>{{ $closing->shortage_note ?: '—' }}</td>
                  <td class="text-nowrap">
                    @if($closing->short_balance > 0)
                      <button type="button"
                              class="btn btn-sm btn-success record-payment-btn mb-1"
                              data-url="{{ route('money-shorts.pay', $closing) }}"
                              data-staff="{{ $closing->user->name ?? 'Staff' }}"
                              data-balance="{{ round($closing->short_balance) }}">
                        <i class="fa fa-money"></i> Record Payment
                      </button>
                      <button type="button"
                              class="btn btn-sm btn-primary record-salary-btn mb-1"
                              data-url="{{ route('money-shorts.salary-deduction', $closing) }}"
                              data-staff="{{ $closing->user->name ?? 'Staff' }}"
                              data-balance="{{ round($closing->short_balance) }}">
                        <i class="fa fa-scissors"></i> Salary Deduction
                      </button>
                    @endif
                    @if($closing->settlements->isNotEmpty())
                      <button type="button" class="btn btn-sm btn-outline-secondary toggle-settlements-btn" data-target="settlements-{{ $closing->id }}">
                        <i class="fa fa-list"></i>
                      </button>
                    @endif
                    <a href="{{ route('day-closing.index', ['date' => $closing->closing_date->format('Y-m-d')]) }}#handover-{{ $closing->id }}" class="btn btn-sm btn-outline-primary" title="View handover">
                      <i class="fa fa-eye"></i>
                    </a>
                  </td>
                </tr>
                @if($closing->settlements->isNotEmpty())
                <tr id="settlements-{{ $closing->id }}" style="display:none;">
                  <td colspan="{{ ($multiBusiness ?? false) ? 11 : 10 }}" class="bg-light">
                    <strong class="small text-uppercase text-muted">Settlement History</strong>
                    <table class="table table-sm table-bordered mb-0 mt-2 bg-white">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Type</th>
                          <th>Amount</th>
                          <th>Method</th>
                          <th>Master Sheet</th>
                          <th>Note</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($closing->settlements->sortByDesc('created_at') as $settlement)
                          <tr class="{{ $settlement->isVoided() ? 'text-muted' : '' }}">
                            <td>{{ $settlement->settlement_date->format('M d, Y') }}</td>
                            <td>
                              {{ $settlement->typeLabel() }}
                              @if($settlement->isVoided())
                                <span class="badge badge-secondary ml-1">Undone</span>
                              @endif
                            </td>
                            <td>{{ money($settlement->amount) }}</td>
                            <td>
                              @if($settlement->isCashPayment())
                                {{ ucfirst(str_replace('_', ' ', $settlement->payment_method ?? 'cash')) }}
                                @if($settlement->payment_provider)
                                  <small class="text-muted">({{ $settlement->payment_provider }})</small>
                                @endif
                              @else
                                —
                              @endif
                            </td>
                            <td>
                              @if($settlement->isCashPayment() && ! $settlement->isVoided())
                                <span class="badge badge-success">Posted</span>
                              @elseif($settlement->isCashPayment())
                                <span class="badge badge-secondary">Removed</span>
                              @else
                                <span class="badge badge-secondary">Not posted</span>
                              @endif
                            </td>
                            <td>{{ $settlement->notes ?: '—' }}</td>
                            <td class="text-nowrap">
                              @if(! $settlement->isVoided())
                              <button type="button"
                                      class="btn btn-sm btn-outline-danger undo-settlement-btn"
                                      data-url="{{ route('money-shorts.undo', $settlement) }}"
                                      data-type="{{ $settlement->typeLabel() }}"
                                      data-amount="{{ number_format($settlement->amount, 0) }}"
                                      data-date="{{ $settlement->settlement_date->format('M d, Y') }}"
                                      data-posted="{{ $settlement->isCashPayment() ? '1' : '0' }}">
                                <i class="fa fa-undo"></i> Undo
                              </button>
                              @else
                                <small>{{ $settlement->voided_at?->format('M d, Y') }}</small>
                              @endif
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </td>
                </tr>
                @endif
              @empty
                <tr>
                  <td colspan="{{ ($multiBusiness ?? false) ? 11 : 10 }}" class="text-center py-4 text-muted">
                    <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
                    No money short records match this filter{{ ($activeBusinessType ?? false) ? ' for this business' : '' }}.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title mb-0"><i class="fa fa-history"></i> Recorded Settlements</h3>
        <span class="text-muted small">All payments and salary deductions recorded here, including undone entries.</span>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0">
            <thead class="thead-light">
              <tr>
                <th>Recorded</th>
                <th>Staff</th>
                <th>Short Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Master Sheet</th>
                <th>Status</th>
                <th>Note</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($settlementHistory as $settlement)
                <tr class="{{ $settlement->isVoided() ? 'bg-light text-muted' : '' }}">
                  <td>
                    {{ $settlement->created_at->format('M d, Y H:i') }}
                    @if($settlement->recorder)
                      <br><small class="text-muted">by {{ $settlement->recorder->name }}</small>
                    @endif
                  </td>
                  <td><strong>{{ $settlement->staff->name ?? 'Unknown' }}</strong></td>
                  <td>{{ $settlement->dayClosing?->closing_date?->format('M d, Y') ?? '—' }}</td>
                  <td>{{ $settlement->typeLabel() }}</td>
                  <td class="{{ $settlement->isVoided() ? '' : 'text-success font-weight-bold' }}">{{ money($settlement->amount) }}</td>
                  <td>
                    @if($settlement->isCashPayment())
                      {{ ucfirst(str_replace('_', ' ', $settlement->payment_method ?? 'cash')) }}
                      @if($settlement->payment_provider)
                        <small>({{ $settlement->payment_provider }})</small>
                      @endif
                    @else
                      —
                    @endif
                  </td>
                  <td>
                    @if($settlement->isCashPayment() && ! $settlement->isVoided())
                      <span class="badge badge-success">Posted {{ $settlement->settlement_date->format('M d') }}</span>
                    @elseif($settlement->isCashPayment())
                      <span class="badge badge-secondary">Removed</span>
                    @else
                      <span class="badge badge-secondary">Not posted</span>
                    @endif
                  </td>
                  <td>
                    @if($settlement->isVoided())
                      <span class="badge badge-warning">Undone</span>
                      @if($settlement->voided_at)
                        <br><small>{{ $settlement->voided_at->format('M d, Y') }}</small>
                      @endif
                    @else
                      <span class="badge badge-success">Active</span>
                    @endif
                  </td>
                  <td>{{ $settlement->notes ?: '—' }}</td>
                  <td class="text-nowrap">
                    @if(! $settlement->isVoided())
                      <button type="button"
                              class="btn btn-sm btn-outline-danger undo-settlement-btn"
                              data-url="{{ route('money-shorts.undo', $settlement) }}"
                              data-type="{{ $settlement->typeLabel() }}"
                              data-amount="{{ number_format($settlement->amount, 0) }}"
                              data-date="{{ $settlement->settlement_date->format('M d, Y') }}"
                              data-posted="{{ $settlement->isCashPayment() ? '1' : '0' }}">
                        <i class="fa fa-undo"></i> Undo
                      </button>
                    @endif
                    @if($settlement->dayClosing)
                      <a href="{{ route('day-closing.index', ['date' => $settlement->dayClosing->closing_date->format('Y-m-d')]) }}#handover-{{ $settlement->dayClosing->id }}" class="btn btn-sm btn-outline-primary" title="View handover">
                        <i class="fa fa-eye"></i>
                      </a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="10" class="text-center py-4 text-muted">
                    No settlements recorded yet.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<form id="moneyShortPaymentForm" method="POST" style="display:none;">
  @csrf
  @if($activeBusinessType ?? false)
    <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
  @endif
  @if(($statusFilter ?? 'all') !== 'all')
    <input type="hidden" name="status" value="{{ $statusFilter }}">
  @endif
  @if(request('search'))
    <input type="hidden" name="search" value="{{ request('search') }}">
  @endif
  <input type="hidden" name="amount" id="paymentAmount">
  <input type="hidden" name="settlement_date" id="paymentDate">
  <input type="hidden" name="payment_method" id="paymentMethod">
  <input type="hidden" name="payment_provider" id="paymentProvider">
  <input type="hidden" name="transaction_reference" id="paymentReference">
  <input type="hidden" name="notes" id="paymentNotes">
</form>

<form id="moneyShortSalaryForm" method="POST" style="display:none;">
  @csrf
  @if($activeBusinessType ?? false)
    <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
  @endif
  @if(($statusFilter ?? 'all') !== 'all')
    <input type="hidden" name="status" value="{{ $statusFilter }}">
  @endif
  @if(request('search'))
    <input type="hidden" name="search" value="{{ request('search') }}">
  @endif
  <input type="hidden" name="amount" id="salaryAmount">
  <input type="hidden" name="settlement_date" id="salaryDate">
  <input type="hidden" name="notes" id="salaryNotes">
</form>

<form id="moneyShortUndoForm" method="POST" style="display:none;">
  @csrf
  @method('DELETE')
  @if($activeBusinessType ?? false)
    <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
  @endif
  @if(($statusFilter ?? 'all') !== 'all')
    <input type="hidden" name="status" value="{{ $statusFilter }}">
  @endif
  @if(request('search'))
    <input type="hidden" name="search" value="{{ request('search') }}">
  @endif
</form>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  const paymentMethods = @json(collect($paymentMethods)->map(fn ($m) => ['key' => $m['key'], 'label' => $m['label']])->values());

  function buildMethodOptions() {
    if (!paymentMethods.length) {
      return '<option value="cash">Physical Cash</option>';
    }
    return paymentMethods.map(function (method) {
      return '<option value="' + method.key + '">' + method.label + '</option>';
    }).join('');
  }

  $('.toggle-settlements-btn').on('click', function () {
    const target = $(this).data('target');
    $('#' + target).toggle();
  });

  $('.record-payment-btn').on('click', function () {
    const url = $(this).data('url');
    const staff = $(this).data('staff');
    const balance = parseFloat($(this).data('balance')) || 0;
    const today = new Date().toISOString().slice(0, 10);

    Swal.fire({
      title: 'Record Payment',
      html: `
        <p class="text-left mb-3">Staff: <strong>${staff}</strong><br>Balance due: <strong>TZS ${balance.toLocaleString()}</strong></p>
        <div class="text-left">
          <div class="form-group">
            <label class="font-weight-bold">Amount Received (TZS)</label>
            <input type="number" id="swal-payment-amount" class="form-control" min="1" max="${balance}" step="1" value="${balance}">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Payment Date</label>
            <input type="date" id="swal-payment-date" class="form-control" value="${today}" max="${today}">
            <small class="text-muted">This amount will post to the Master Sheet for this date.</small>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Payment Method</label>
            <select id="swal-payment-method" class="form-control">${buildMethodOptions()}</select>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Provider / Reference</label>
            <input type="text" id="swal-payment-provider" class="form-control" placeholder="Optional">
            <input type="text" id="swal-payment-reference" class="form-control mt-2" placeholder="Transaction reference (optional)">
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold">Note</label>
            <textarea id="swal-payment-notes" class="form-control" rows="2" placeholder="Optional note"></textarea>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Record & Post to Master Sheet',
      width: 520,
      preConfirm: () => {
        const amount = parseFloat(document.getElementById('swal-payment-amount').value);
        const date = document.getElementById('swal-payment-date').value;
        if (!amount || amount <= 0 || amount > balance) {
          Swal.showValidationMessage('Enter a valid amount up to the balance due.');
          return false;
        }
        if (!date) {
          Swal.showValidationMessage('Choose the payment date.');
          return false;
        }
        return {
          amount: amount,
          date: date,
          method: document.getElementById('swal-payment-method').value,
          provider: document.getElementById('swal-payment-provider').value.trim(),
          reference: document.getElementById('swal-payment-reference').value.trim(),
          notes: document.getElementById('swal-payment-notes').value.trim()
        };
      }
    }).then((result) => {
      if (!result.isConfirmed) return;
      const form = $('#moneyShortPaymentForm');
      form.attr('action', url);
      $('#paymentAmount').val(result.value.amount);
      $('#paymentDate').val(result.value.date);
      $('#paymentMethod').val(result.value.method);
      $('#paymentProvider').val(result.value.provider);
      $('#paymentReference').val(result.value.reference);
      $('#paymentNotes').val(result.value.notes);
      form.submit();
    });
  });

  $('.record-salary-btn').on('click', function () {
    const url = $(this).data('url');
    const staff = $(this).data('staff');
    const balance = parseFloat($(this).data('balance')) || 0;
    const today = new Date().toISOString().slice(0, 10);

    Swal.fire({
      title: 'Deduct From Salary',
      html: `
        <p class="text-left mb-3">Staff: <strong>${staff}</strong><br>Balance due: <strong>TZS ${balance.toLocaleString()}</strong></p>
        <div class="text-left">
          <div class="form-group">
            <label class="font-weight-bold">Amount to Deduct (TZS)</label>
            <input type="number" id="swal-salary-amount" class="form-control" min="1" max="${balance}" step="1" value="${balance}">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Deduction Date</label>
            <input type="date" id="swal-salary-date" class="form-control" value="${today}">
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold">Note</label>
            <textarea id="swal-salary-notes" class="form-control" rows="2" placeholder="e.g. Deduct from June salary"></textarea>
          </div>
          <small class="text-muted d-block mt-2">Salary deductions clear the staff balance but do not add cash to the Master Sheet.</small>
        </div>
      `,
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Record Salary Deduction',
      width: 520,
      preConfirm: () => {
        const amount = parseFloat(document.getElementById('swal-salary-amount').value);
        const date = document.getElementById('swal-salary-date').value;
        if (!amount || amount <= 0 || amount > balance) {
          Swal.showValidationMessage('Enter a valid amount up to the balance due.');
          return false;
        }
        return {
          amount: amount,
          date: date,
          notes: document.getElementById('swal-salary-notes').value.trim()
        };
      }
    }).then((result) => {
      if (!result.isConfirmed) return;
      const form = $('#moneyShortSalaryForm');
      form.attr('action', url);
      $('#salaryAmount').val(result.value.amount);
      $('#salaryDate').val(result.value.date);
      $('#salaryNotes').val(result.value.notes);
      form.submit();
    });
  });

  $('.undo-settlement-btn').on('click', function () {
    const url = $(this).data('url');
    const type = $(this).data('type');
    const amount = $(this).data('amount');
    const date = $(this).data('date');
    const posted = $(this).data('posted') === 1 || $(this).data('posted') === '1';
    const masterSheetNote = posted
      ? '<br><small class="text-danger">This will remove the amount from the Master Sheet for ' + date + '.</small>'
      : '<br><small class="text-muted">This was not posted to the Master Sheet.</small>';

    Swal.fire({
      title: 'Undo Settlement?',
      html: 'Reverse <strong>' + type + '</strong> of <strong>TZS ' + amount + '</strong>?' + masterSheetNote,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#940000',
      confirmButtonText: 'Yes, undo',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (!result.isConfirmed) return;
      const form = $('#moneyShortUndoForm');
      form.attr('action', url);
      form.submit();
    });
  });
});
</script>
@endsection
