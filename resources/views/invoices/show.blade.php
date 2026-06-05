@extends('layouts.app')

@section('title', 'Invoice ' . $sale->reference_no)

@section('content')
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
@endphp

<div class="app-title d-print-none">
  <div>
    <h1><i class="fa fa-file-text-o"></i> Invoice {{ $sale->reference_no }}</h1>
    <p>Tax invoice for customer</p>
  </div>
  <div>
    <a href="{{ $backRoute }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> {{ $backLabel }}</a>
    <button type="button" class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print Invoice</button>
    @if($sale->payment_status === 'paid' || (float) $sale->amount_paid > 0)
      <a href="{{ route('sales.show', $sale) }}" class="btn btn-info"><i class="fa fa-print"></i> Print Receipt</a>
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
        class="btn btn-success open-payment-modal-btn"
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

<div class="row">
  <div class="col-md-12">
    <div class="tile invoice-document">
      <div class="invoice-header row mb-4">
        <div class="col-7">
          <h2 class="invoice-brand mb-1">{{ $business->name }}</h2>
          @if($branch)
            <div class="text-muted">{{ $branch->name }}</div>
            @if($branch->address)<div>{{ $branch->address }}</div>@endif
            @if($branch->phone)<div>Tel: {{ $branch->phone }}</div>@endif
          @else
            @if($business->address)<div>{{ $business->address }}</div>@endif
            @if($business->phone)<div>Tel: {{ $business->phone }}</div>@endif
          @endif
          @if($business->email)<div>{{ $business->email }}</div>@endif
          <div><strong>TIN:</strong> {{ $business->tin_number ?? 'N/A' }}</div>
        </div>
        <div class="col-5 text-right">
          <h1 class="invoice-title">INVOICE</h1>
          <table class="invoice-meta-table ml-auto">
            <tr><td class="text-muted pr-3">Invoice No.</td><td><strong>{{ $sale->reference_no }}</strong></td></tr>
            <tr><td class="text-muted pr-3">Date</td><td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td></tr>
            <tr><td class="text-muted pr-3">Prepared by</td><td>{{ $sale->user->name }}</td></tr>
            <tr>
              <td class="text-muted pr-3">Status</td>
              <td><span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span></td>
            </tr>
          </table>
        </div>
      </div>

      <div class="row invoice-info mb-4">
        <div class="col-md-6">
          <h6 class="text-uppercase text-muted mb-2">Bill To</h6>
          @if($sale->customer_name)
            <address class="mb-0">
              <strong>{{ $sale->customer_name }}</strong><br>
              @if($sale->customer_phone)Phone: {{ $sale->customer_phone }}<br>@endif
              @if($sale->customer && $sale->customer->email)Email: {{ $sale->customer->email }}<br>@endif
            </address>
          @else
            <address class="mb-0 text-muted">Walk-in Customer</address>
          @endif
        </div>
        @if($sale->due_date && $balanceDue > 0)
        <div class="col-md-6 text-md-right">
          <h6 class="text-uppercase text-muted mb-2">Payment Due</h6>
          <p class="mb-0"><strong>{{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</strong></p>
        </div>
        @endif
      </div>

      <div class="table-responsive">
        <table class="table table-bordered invoice-lines">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ __('tables.columns.description') }}</th>
              <th>{{ __('tables.columns.sku') }}</th>
              <th class="text-right">Qty</th>
              <th class="text-right">Unit Price</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sale->items as $index => $line)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                  @if($line->service_id)
                    {{ $line->line_description ?: $line->service?->name ?? 'Service' }}
                  @else
                    {{ $line->item->name ?? 'Item' }}
                  @endif
                </td>
                <td>{{ $line->item->sku ?? '—' }}</td>
                <td class="text-right">{{ number_format((float) $line->quantity, 0) }}</td>
                <td class="text-right">{{ money($line->unit_price) }}</td>
                <td class="text-right">{{ money($line->subtotal) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-right">Subtotal</th>
              <th class="text-right">{{ money($sale->total_amount) }}</th>
            </tr>
            @if((float) $sale->amount_paid > 0)
            <tr>
              <th colspan="5" class="text-right text-success">Amount Paid</th>
              <th class="text-right text-success">{{ money($sale->amount_paid) }}</th>
            </tr>
            @endif
            @if($balanceDue > 0)
            <tr>
              <th colspan="5" class="text-right text-danger">Balance Due</th>
              <th class="text-right text-danger">{{ money($balanceDue) }}</th>
            </tr>
            @endif
            <tr class="invoice-grand-total">
              <th colspan="5" class="text-right">Total (TZS)</th>
              <th class="text-right">{{ money($sale->total_amount) }}</th>
            </tr>
          </tfoot>
        </table>
      </div>

      @if($sale->notes)
      <div class="mt-3">
        <h6 class="text-uppercase text-muted">Notes</h6>
        <p class="mb-0">{!! nl2br(e($sale->notes)) !!}</p>
      </div>
      @endif

      @if(($paymentReceiveDetails ?? collect())->isNotEmpty())
      <div class="mt-4">
        <h6 class="text-uppercase text-muted">Payment Details</h6>
        <p class="small text-muted mb-2">Use the details below when paying this invoice.</p>
        <table class="table table-sm table-bordered mb-0">
          <thead class="thead-light">
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
      @endif

      @if($sale->payments->count() > 0)
      <div class="mt-4 d-print-none">
        <h6>Payment History</h6>
        <table class="table table-sm table-bordered">
          <thead class="bg-light">
            <tr>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.method') }}</th>
              <th>{{ __('tables.columns.reference') }}</th>
              <th class="text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sale->payments as $payment)
              <tr>
                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                <td>{{ $payment->transaction_reference ?? ($payment->payment_provider ?? '—') }}</td>
                <td class="text-right">{{ money($payment->amount) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif

      <div class="invoice-footer mt-4 pt-3 border-top text-center text-muted">
        <small>Thank you for your business.</small>
      </div>
    </div>
  </div>
</div>

<style>
  .invoice-brand { color: #940000; font-weight: 700; }
  .invoice-title { color: #940000; font-size: 2rem; font-weight: 800; letter-spacing: 2px; margin-bottom: 1rem; }
  .invoice-meta-table td { padding: 2px 0; }
  .invoice-lines thead th { background: #940000; color: #fff; border-color: #7a0000; }
  .invoice-grand-total th { background: #f8f9fa; font-size: 1.1rem; }
  @media print {
    .app-title, .d-print-none, .app-sidebar, .app-header, .alert { display: none !important; }
    .app-content { margin-left: 0 !important; margin-top: 0 !important; padding: 0 !important; }
    .tile { box-shadow: none !important; border: none !important; }
    .invoice-document { padding: 0; }
  }
</style>

@include('sales.partials.payment-modal')
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('sales.partials.customer-picker-scripts')
@include('sales.partials.payment-modal-scripts')
@endsection
