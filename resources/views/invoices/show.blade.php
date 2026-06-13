@extends('layouts.app')

@section('title', 'Invoice ' . $sale->reference_no)

@section('content')
@include('partials.official-report-styles')

@php
  $backRoute = $backRoute ?? route('invoices.index');
  $backLabel = $backLabel ?? 'All Invoices';
  $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
  $statusLabel = match($sale->payment_status) {
    'paid' => 'PAID',
    'partial' => 'PARTIALLY PAID',
    'debt' => 'CREDIT / UNPAID',
    'pending' => 'UNPAID',
    default => strtoupper($sale->payment_status),
  };
  $statusClass = match($sale->payment_status) {
    'paid' => 'success',
    'partial' => 'info',
    'debt', 'pending' => 'danger',
    default => 'secondary',
  };
  $stampClass = match($sale->payment_status) {
    'paid' => 'stamp-paid',
    'partial' => 'stamp-pending',
    default => 'stamp-pending',
  };
  $logoUrl = $business->logo_path ? asset('storage/'.$business->logo_path) : null;
@endphp

<div class="official-report">
  <div class="app-title d-print-none">
    <div>
      <h1><i class="fa fa-file-text-o"></i> Invoice {{ $sale->reference_no }}</h1>
      <p>Tax invoice for customer</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Invoices</a></li>
      <li class="breadcrumb-item active">{{ $sale->reference_no }}</li>
    </ul>
    <div class="mt-2">
      <a href="{{ $backRoute }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i> {{ $backLabel }}</a>
      @if($sale->payment_status === 'paid' || (float) $sale->amount_paid > 0)
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-info btn-sm"><i class="fa fa-print"></i> Print Receipt</a>
      @endif
      @if(in_array($sale->payment_status, ['pending', 'partial', 'debt']) && $balanceDue > 0)
        @php
          $invoicePayItems = $sale->items->map(fn ($si) => [
            'id' => $si->id,
            'name' => $si->service_id
              ? ($si->line_description ?: $si->service?->name ?? 'Service')
              : ($si->item->name ?? 'Item'),
            'qty' => (float) $si->quantity,
            'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
          ])->values();
        @endphp
        <button type="button"
          class="btn btn-success btn-sm open-payment-modal-btn"
          data-sale-id="{{ $sale->id }}"
          data-ref="{{ e($sale->reference_no) }}"
          data-total="{{ $sale->total_amount }}"
          data-paid="{{ $sale->amount_paid }}"
          data-customer-id="{{ $sale->customer_id ?? '' }}"
          data-customer-name="{{ e($sale->customer_name ?? '') }}"
          data-customer-phone="{{ e($sale->customer_phone ?? '') }}"
          data-due-date="{{ $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->format('Y-m-d') : '' }}"
          data-items='@json($invoicePayItems)'>
          <i class="fa fa-money"></i> Record Payment
        </button>
      @endif
    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success d-print-none">{{ session('success') }}</div>
  @endif

  @if($sale->payment_status === 'paid')
  <div class="alert alert-success d-print-none">
    <i class="fa fa-check-circle"></i> This invoice is <strong>fully paid</strong>.
    Give the customer a receipt — click <strong>Print Receipt</strong> above.
  </div>
  @elseif($balanceDue > 0)
  <div class="alert alert-warning d-print-none">
    <i class="fa fa-info-circle"></i> Balance due: <strong>{{ money($balanceDue) }}</strong>.
    Click <strong>Record Payment</strong> when the customer pays — stock is deducted when payment is recorded.
  </div>
  @endif

  @if(!$sale->stock_deducted && $sale->isInvoice())
  <div class="alert alert-info d-print-none py-2">
    <small><i class="fa fa-cubes"></i> Stock not yet deducted — items will leave inventory when payment is recorded.</small>
  </div>
  @endif

  <div class="tile report-sheet">
    <div class="report-header-center">
      @if($logoUrl)
      <img src="{{ $logoUrl }}" alt="{{ $business->name }}">
      @endif
      <h1>{{ $business->name }}</h1>
      <div class="biz-contact-info">
        @if($business->address){{ $business->address }}@endif
        @if($business->phone) | Mobile: {{ $business->phone }}@endif
        @if($business->email) | Email: {{ $business->email }}@endif
        @if($business->tin_number) | TIN: {{ $business->tin_number }}@endif
        @if($business->vat_number) | VAT: {{ $business->vat_number }}@endif
      </div>
      <div class="operations-title">
        @if($branch){{ strtoupper($branch->name) }} — @endif TAX INVOICE
      </div>
      <hr class="accent-divider">
    </div>

    <div class="report-sub-meta">
      <span>Prepared by: {{ $sale->user->name ?? 'Staff' }}</span>
      <span>Ref: {{ $sale->reference_no }}</span>
      <span>Date: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</span>
    </div>

    <div class="title-area">
      <h2 class="main-report-title">Invoice {{ $sale->reference_no }}</h2>
      <div class="official-stamp {{ $stampClass }}">{{ $statusLabel }}</div>
    </div>

    <div class="text-center mb-4 d-print-none">
      <button type="button" onclick="window.print()" class="btn btn-print shadow-sm">
        <i class="fa fa-print"></i> Print Invoice / PDF
      </button>
      <div class="mt-2 text-muted" style="font-size:0.85rem;">
        <i class="fa fa-info-circle"></i> Use your browser print dialog to save as PDF or print — the page prints as shown.
      </div>
    </div>

    <div class="report-stats-grid">
      <div>
        <div class="stats-card-title">Bill To</div>
        @if($sale->customer_name)
          <div class="stats-row"><strong>Name:</strong> <span>{{ $sale->customer_name }}</span></div>
          @if($sale->customer_phone)
          <div class="stats-row"><strong>Phone:</strong> <span>{{ $sale->customer_phone }}</span></div>
          @endif
          @if($sale->customer && $sale->customer->email)
          <div class="stats-row"><strong>Email:</strong> <span>{{ $sale->customer->email }}</span></div>
          @endif
        @else
          <div class="stats-row"><strong>Customer:</strong> <span>Walk-in Customer</span></div>
        @endif
      </div>
      <div>
        <div class="stats-card-title">Invoice Summary</div>
        <div class="stats-row"><strong>Status:</strong> <span>{{ $statusLabel }}</span></div>
        <div class="stats-row"><strong>Total:</strong> <span class="amount-accent">{{ money($sale->total_amount) }}</span></div>
        @if((float) $sale->amount_paid > 0)
        <div class="stats-row"><strong>Amount Paid:</strong> <span>{{ money($sale->amount_paid) }}</span></div>
        @endif
        @if($balanceDue > 0)
        <div class="stats-row"><strong>Balance Due:</strong> <span class="text-danger font-weight-bold">{{ money($balanceDue) }}</span></div>
        @if($sale->due_date)
        <div class="stats-row"><strong>Due Date:</strong> <span>{{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</span></div>
        @endif
        @endif
      </div>
    </div>

    <div class="stats-card-title mb-2">Invoice Items</div>
    <div class="table-responsive">
      <table class="report-table mb-0 invoice-lines">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th class="text-left">{{ __('tables.columns.description') }}</th>
            <th style="width:70px;">Qty</th>
            <th style="width:110px;">Unit Price</th>
            <th style="width:120px;">Amount</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sale->items as $index => $line)
            <tr>
              <td class="text-muted-row">{{ $index + 1 }}</td>
              <td class="text-left">
                @if($line->service_id)
                  {{ $line->line_description ?: $line->service?->name ?? 'Service' }}
                @else
                  {{ $line->item->name ?? 'Item' }}
                @endif
              </td>
              <td>{{ number_format((float) $line->quantity, 0) }}</td>
              <td>{{ money($line->unit_price) }}</td>
              <td class="amount-accent">{{ money($line->subtotal) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          @include('invoices.partials.totals-footer', [
            'sale' => $sale,
            'business' => $business,
            'balanceDue' => $balanceDue,
          ])
        </tfoot>
      </table>
    </div>

    @if($sale->notes)
    <div class="mt-3">
      <div class="stats-card-title mb-2">Notes</div>
      <p class="mb-0">{!! nl2br(e($sale->notes)) !!}</p>
    </div>
    @endif

    @if(($paymentReceiveDetails ?? collect())->isNotEmpty())
    <div class="mt-4">
      <div class="stats-card-title mb-2">Payment Details</div>
      <p class="small text-muted mb-2">Use the details below when paying this invoice.</p>
      <div class="table-responsive">
        <table class="report-table mb-0">
          <thead>
            <tr>
              <th>{{ __('tables.columns.method') }}</th>
              <th>{{ __('tables.columns.platform') }}</th>
              <th>Pay Number / Account</th>
              <th>{{ __('tables.columns.name') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($paymentReceiveDetails as $detail)
              <tr>
                <td>{{ $detail['method_label'] }}</td>
                <td>{{ $detail['platform'] }}</td>
                <td><strong>{{ $detail['pay_number'] ?: '—' }}</strong></td>
                <td>{{ $detail['account_name'] ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    @if($sale->payments->count() > 0)
    <div class="mt-4 d-print-none">
      <div class="stats-card-title mb-2">Payment History</div>
      <div class="table-responsive">
        <table class="report-table mb-0">
          <thead>
            <tr>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.method') }}</th>
              <th>{{ __('tables.columns.reference') }}</th>
              <th>{{ __('tables.columns.amount') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sale->payments as $payment)
              <tr>
                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                <td>{{ $payment->transaction_reference ?? ($payment->payment_provider ?? '—') }}</td>
                <td class="amount-accent">{{ money($payment->amount) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    <div class="mt-4 pt-4 border-top row">
      <div class="col-md-6">
        <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Authorized signature</small>
        <div class="mt-2 font-weight-bold" style="font-size:1.05rem; color: var(--report-accent);">{{ $sale->user->name ?? 'Staff' }}</div>
        <div class="mt-2 text-muted">_______________________________________</div>
      </div>
      <div class="col-md-6 text-md-right mt-3 mt-md-0">
        <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Customer copy</small>
        <div class="mt-3 text-muted">_______________________________________</div>
      </div>
    </div>

    <div class="text-center mt-4 small text-muted">
      Generated {{ now()->format('d M Y, H:i') }} · Thank you for your business.<br>
      Powered By <strong>EmCa Technologies</strong> — <a href="https://www.emca.tech" target="_blank" rel="noopener">www.emca.tech</a>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('sales.partials.customer-picker-scripts')
@include('sales.partials.payment-modal-scripts')
@endsection
