@extends('layouts.app')

@section('title', 'Payment Report - Admin')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Payment Report</h1>
    <p>Track subscription invoices and payments from all businesses on the platform.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Payments</a></li>
  </ul>
</div>

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-file-text-o fa-3x"></i>
      <div class="info">
        <h4>Total Invoiced</h4>
        <p><b>TZS {{ number_format($summary['total_invoiced'], 0) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info">
        <h4>Collected (Paid)</h4>
        <p><b>TZS {{ number_format($summary['total_paid'], 0) }}</b></p>
        <small>{{ $summary['paid_count'] }} invoice(s)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>Outstanding</h4>
        <p><b>TZS {{ number_format($summary['total_outstanding'], 0) }}</b></p>
        <small>{{ $summary['pending_count'] + $summary['notified_count'] }} unpaid</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-envelope-o fa-3x"></i>
      <div class="info">
        <h4>Invoices Sent</h4>
        <p><b>{{ $summary['notified_count'] }}</b></p>
        <small>{{ $summary['pending_count'] }} still pending</small>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile mb-3">
      <h3 class="tile-title">Filters</h3>
      <div class="tile-body">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="row">
          <div class="col-md-3 form-group">
            <label class="control-label">Billing Month</label>
            <input type="month" name="month" class="form-control" value="{{ request('month', $month?->format('Y-m')) }}">
          </div>
          <div class="col-md-3 form-group">
            <label class="control-label">Business</label>
            <select name="business_id" class="form-control">
              <option value="">All businesses</option>
              @foreach($businesses as $business)
              <option value="{{ $business->id }}" {{ (string) request('business_id') === (string) $business->id ? 'selected' : '' }}>{{ $business->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 form-group">
            <label class="control-label">Status</label>
            <select name="status" class="form-control">
              <option value="">All statuses</option>
              <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Payment</option>
              <option value="notified" {{ request('status') === 'notified' ? 'selected' : '' }}>Invoice Sent</option>
              <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
            </select>
          </div>
          <div class="col-md-2 form-group">
            <label class="control-label">Search</label>
            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Business or invoice #">
          </div>
          <div class="col-md-2 form-group d-flex align-items-end">
            <button type="submit" class="btn btn-primary mr-2"><i class="fa fa-filter"></i> Filter</button>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="tile-title">Business Payments</h3>
        <p>
          <button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#manualInvoiceModal"><i class="fa fa-file-text-o"></i> Create Invoice</button>
          <form method="POST" action="{{ route('admin.payments.generate') }}" class="d-inline" onsubmit="return confirm('Generate invoices for all active businesses for the selected month?');">
            @csrf
            <input type="hidden" name="month" value="{{ request('month', now()->format('Y-m')) }}">
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa fa-plus"></i> Generate Invoices</button>
          </form>
        </p>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0">
            <thead>
              <tr>
                <th>Invoice</th>
                <th>{{ __('tables.columns.business') }}</th>
                <th>Billing Month</th>
                <th>Plan</th>
                <th>Billing</th>
                <th class="text-right">Amount</th>
                <th>{{ __('tables.columns.status') }}</th>
                <th>Paid On</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($invoices as $invoice)
              <tr>
                <td><strong>{{ $invoice->invoice_number }}</strong></td>
                <td>
                  {{ $invoice->business->name ?? '—' }}
                  @if($invoice->business?->expiry_date)
                  <br><small class="text-muted">Expires {{ $invoice->business->expiry_date->format('M d, Y') }}</small>
                  @endif
                </td>
                <td>
                  {{ $invoice->billingMonthLabel() }}
                  @if($invoice->is_manual)
                  <br><small class="text-muted">{{ $invoice->quantity }} × TZS {{ number_format((float) $invoice->unit_price, 0) }}@if($invoice->description) · {{ \Illuminate\Support\Str::limit($invoice->description, 40) }}@endif</small>
                  @endif
                </td>
                <td>{{ $invoice->plan->name ?? '—' }}</td>
                <td><small>{{ $invoice->billingModelLabel() }}</small></td>
                <td class="text-right"><strong>TZS {{ number_format((float) $invoice->amount, 0) }}</strong></td>
                <td><span class="badge badge-{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                <td>
                  @if($invoice->paid_at)
                    {{ $invoice->paid_at->format('M d, Y') }}
                    @if($invoice->payment_reference)
                    <br><small class="text-muted">{{ $invoice->payment_reference }}</small>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td class="text-center text-nowrap">
                  <a href="{{ route('admin.payments.pdf', $invoice) }}" class="btn btn-outline-secondary btn-sm" title="Download"><i class="fa fa-download"></i></a>
                  <form method="POST" action="{{ route('admin.payments.resend', $invoice) }}" class="d-inline" onsubmit="return confirm('Resend invoice email to business?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-info btn-sm" title="Resend email"><i class="fa fa-envelope"></i></button>
                  </form>
                  @if($invoice->status !== \App\Models\PlatformBillingInvoice::STATUS_PAID)
                  <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#markPaidModal{{ $invoice->id }}">
                    <i class="fa fa-check"></i> Mark Paid
                  </button>
                  @else
                  @if($invoice->business)
                  <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editExpiryModal{{ $invoice->id }}" title="Edit paid-until date">
                    <i class="fa fa-calendar"></i> Edit Date
                  </button>
                  @endif
                  <br><span class="text-muted small">By {{ $invoice->markedPaidByUser->name ?? 'Admin' }}</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="9" class="text-center text-muted py-4">
                  No payment records found. Use <strong>Generate Invoices</strong> to create billing records for a month, or adjust your filters.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($invoices->hasPages())
        <div class="mt-3">
          {{ $invoices->links() }}
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

@foreach($invoices as $invoice)
@if($invoice->status !== \App\Models\PlatformBillingInvoice::STATUS_PAID)
<div class="modal fade" id="markPaidModal{{ $invoice->id }}" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.payments.mark-paid', $invoice) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Record Payment — {{ $invoice->business->name ?? 'Business' }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            Invoice <strong>{{ $invoice->invoice_number }}</strong> · {{ $invoice->billingMonthLabel() }} ·
            <strong>TZS {{ number_format((float) $invoice->amount, 0) }}</strong>
          </p>
          <div class="form-group">
            <label class="control-label">Payment Reference</label>
            <input type="text" name="payment_reference" class="form-control" maxlength="120" placeholder="M-Pesa ref, bank slip, receipt #">
          </div>
          <div class="form-group">
            <label class="control-label">Notes</label>
            <textarea name="payment_notes" class="form-control" rows="3" maxlength="1000" placeholder="Optional notes about this payment"></textarea>
          </div>
          <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="extend_subscription_{{ $invoice->id }}" name="extend_subscription" value="1" checked>
            <label class="custom-control-label" for="extend_subscription_{{ $invoice->id }}">Extend business subscription after payment</label>
          </div>
          @php
            $payBusiness = $invoice->business;
            $payDefaultMonths = max(1, (int) ($invoice->is_manual && $invoice->quantity ? $invoice->quantity : ($payBusiness?->plan?->duration_months ?? 1)));
            $payBase = $payBusiness?->expiry_date && \Carbon\Carbon::parse($payBusiness->expiry_date)->isFuture()
              ? \Carbon\Carbon::parse($payBusiness->expiry_date)
              : now();
          @endphp
          <div class="form-group mb-0">
            <label class="control-label" for="paid_until_{{ $invoice->id }}">Paid until</label>
            <input type="text" name="paid_until" id="paid_until_{{ $invoice->id }}" class="form-control js-date-picker" value="{{ $payBase->copy()->addMonthsNoOverflow($payDefaultMonths)->toDateString() }}" required>
            <div class="mt-2">
              <small class="text-muted mr-1">Months paid:</small>
              @foreach([1, 2, 3, 6, 12] as $m)
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 mr-1 mb-1 js-date-add-months" data-target="#paid_until_{{ $invoice->id }}" data-from="{{ $payBase->toDateString() }}" data-months="{{ $m }}">{{ $m }} {{ $m === 1 ? 'month' : 'months' }}</button>
              @endforeach
            </div>
            <small class="text-muted d-block">
              Current expiry: {{ $payBusiness?->expiry_date ? \Carbon\Carbon::parse($payBusiness->expiry_date)->format('d M Y') : 'not set' }}. Pick any date, or use the month buttons.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Confirm Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>
@elseif($invoice->business)
<div class="modal fade" id="editExpiryModal{{ $invoice->id }}" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.payments.update-expiry', $invoice) }}">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Edit Paid-Until Date — {{ $invoice->business->name }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            Invoice <strong>{{ $invoice->invoice_number }}</strong> · paid {{ $invoice->paid_at?->format('d M Y') }}
          </p>
          <div class="form-group mb-0">
            <label class="control-label" for="edit_paid_until_{{ $invoice->id }}">Paid until</label>
            <input type="text" name="paid_until" id="edit_paid_until_{{ $invoice->id }}" class="form-control js-date-picker" value="{{ $invoice->business->expiry_date?->toDateString() ?? now()->toDateString() }}" required>
            <div class="mt-2">
              <small class="text-muted mr-1">Add:</small>
              @foreach([1, 3, 6, 12] as $m)
              <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 mr-1 mb-1 js-date-add-months" data-target="#edit_paid_until_{{ $invoice->id }}" data-months="{{ $m }}">+{{ $m }} {{ $m === 1 ? 'month' : 'months' }}</button>
              @endforeach
            </div>
            <small class="text-muted d-block">Current expiry: {{ $invoice->business->expiry_date?->format('d M Y') ?? 'not set' }}.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-calendar"></i> Save Date</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endforeach

<div class="modal fade" id="manualInvoiceModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.payments.manual') }}" id="manualInvoiceForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-file-text-o"></i> Create Invoice</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="control-label">Business</label>
            <select name="business_id" id="manualBusiness" class="form-control" required>
              <option value="">Select business</option>
              @foreach($invoiceBusinesses as $b)
                @php $bMonths = max(1, (int) ($b->plan?->duration_months ?? 1)); @endphp
                <option value="{{ $b->id }}"
                        data-monthly="{{ round($b->effectiveBillingPrice() / $bMonths, 2) }}"
                        data-plan="{{ $b->plan?->name }}"
                        data-email="{{ $b->email }}">{{ $b->name }}{{ $b->is_active ? '' : ' (inactive)' }}</option>
              @endforeach
            </select>
            <small class="text-muted" id="manualBusinessInfo"></small>
          </div>
          <div class="form-row">
            <div class="form-group col-sm-6">
              <label class="control-label">Billing Month</label>
              <input type="month" name="billing_month" class="form-control" value="{{ now()->format('Y-m') }}" required>
            </div>
            <div class="form-group col-sm-6">
              <label class="control-label">Months (Qnty)</label>
              <input type="number" name="quantity" id="manualQty" class="form-control" min="1" max="36" value="1" required>
              <div class="mt-1">
                @foreach([1, 3, 6, 12] as $m)
                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 mr-1 js-manual-qty" data-qty="{{ $m }}">{{ $m }}</button>
                @endforeach
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">Unit Price (TZS per month)</label>
            <input type="number" name="unit_price" id="manualUnitPrice" class="form-control" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label class="control-label">Description <small class="text-muted">(optional)</small></label>
            <input type="text" name="description" class="form-control" maxlength="255" placeholder="e.g. Enterprise plan subscription — 3 months">
            <small class="text-muted">Leave empty to use "&lt;Plan&gt; plan subscription — N months".</small>
          </div>
          <div class="alert alert-light border mb-3 py-2 d-flex justify-content-between">
            <span>Total</span><strong id="manualTotal">TZS 0</strong>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="manualSendNow" name="send_now" value="1" checked>
            <label class="custom-control-label" for="manualSendNow">Email the invoice PDF to the business now</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Create Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('partials.date-picker')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var business = document.getElementById('manualBusiness');
  var qty = document.getElementById('manualQty');
  var price = document.getElementById('manualUnitPrice');
  var total = document.getElementById('manualTotal');
  var info = document.getElementById('manualBusinessInfo');
  if (!business) return;

  function updateTotal() {
    var value = (parseFloat(price.value) || 0) * (parseInt(qty.value, 10) || 0);
    total.textContent = 'TZS ' + Math.round(value).toLocaleString('en-US');
  }

  business.addEventListener('change', function () {
    var option = business.options[business.selectedIndex];
    if (option && option.value) {
      price.value = option.dataset.monthly || '';
      var parts = [];
      if (option.dataset.plan) parts.push('Plan: ' + option.dataset.plan);
      parts.push(option.dataset.email ? 'Email: ' + option.dataset.email : 'No email on file');
      info.textContent = parts.join(' · ');
    } else {
      info.textContent = '';
    }
    updateTotal();
  });

  document.querySelectorAll('.js-manual-qty').forEach(function (btn) {
    btn.addEventListener('click', function () {
      qty.value = btn.dataset.qty;
      updateTotal();
    });
  });

  qty.addEventListener('input', updateTotal);
  price.addEventListener('input', updateTotal);

  document.getElementById('manualInvoiceForm').addEventListener('submit', function (e) {
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
  });
});
</script>
@endsection
