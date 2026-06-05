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
                <td>{{ $invoice->billingMonthLabel() }}</td>
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
                  <span class="text-muted small">By {{ $invoice->markedPaidByUser->name ?? 'Admin' }}</span>
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
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="extend_subscription_{{ $invoice->id }}" name="extend_subscription" value="1" checked>
            <label class="custom-control-label" for="extend_subscription_{{ $invoice->id }}">Extend business subscription after payment</label>
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
@endif
@endforeach
@endsection
