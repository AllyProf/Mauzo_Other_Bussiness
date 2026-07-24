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

    <div class="text-center mb-3 d-print-none">
      <button type="button" onclick="window.print()" class="btn btn-print shadow-sm">
        <i class="fa fa-print"></i> Print Invoice / PDF
      </button>
    </div>

    <div class="invoice-bill-bar">
      <div class="invoice-bill-left">
        <span class="invoice-bill-kicker">Bill To</span>
        <span class="invoice-bill-sep">:</span>
        <strong class="invoice-bill-customer">{{ $sale->customer_name ?: 'Walk-in Customer' }}</strong>
        @if($sale->customer_phone)
          <span class="invoice-bill-phone">· {{ $sale->customer_phone }}</span>
        @endif
      </div>
      @if($balanceDue > 0)
      <div class="invoice-bill-right">
        <span class="invoice-bill-kicker">Balance Due</span>
        <span class="invoice-bill-sep">:</span>
        <strong class="invoice-bill-due">{{ money($balanceDue) }}</strong>
        @if($sale->due_date)
          <span class="invoice-bill-phone">· {{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</span>
        @endif
      </div>
      @endif
    </div>

    <div class="table-responsive">
      <table class="report-table mb-0 invoice-lines invoice-lines-compact">
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
                  @if($line->itemPackaging?->packagingType?->name)
                    <span class="text-muted font-weight-normal"> · {{ $line->itemPackaging->packagingType->name }}</span>
                  @endif
                @endif
              </td>
              <td>
                {{ number_format((float) $line->quantity, 0) }}
                @if(! $line->service_id && $line->itemPackaging?->packagingType?->name)
                  <span class="text-muted"> {{ $line->itemPackaging->packagingType->name }}</span>
                @endif
              </td>
              <td>{{ money($line->unit_price) }}</td>
              <td class="amount-accent">{{ money($line->subtotal) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @include('invoices.partials.totals-block', [
      'sale' => $sale,
      'business' => $business,
      'balanceDue' => $balanceDue,
    ])

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

    <div class="invoice-signature-block">
      <div class="invoice-sign-col">
        <div class="invoice-sign-label">For {{ $business->name }}</div>
        <div class="invoice-mcharazo">{{ explode(' ', trim($business->name ?: 'Sindato'))[0] }}</div>
        <div class="invoice-sign-line"></div>
        <div class="invoice-sign-caption">Authorized Signature</div>
      </div>
      <div class="invoice-sign-col invoice-sign-col-right">
        <div class="invoice-sign-label">Received By</div>
        <div class="invoice-sign-space"></div>
        <div class="invoice-sign-line"></div>
        <div class="invoice-sign-caption">Customer Signature / Stamp</div>
      </div>
    </div>

    <div class="text-center mt-4 small text-muted invoice-footer-note">
      Generated {{ now()->format('d M Y, H:i') }} · Thank you for your business.<br>
      Powered By <strong>EmCa Technologies</strong> — <a href="https://www.emca.tech" target="_blank" rel="noopener">www.emca.tech</a>
    </div>
  </div>
</div>

@include('sales.partials.payment-modal')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">

<style>
  .official-report .report-sheet {
    position: relative;
  }

  .official-report .invoice-bill-bar {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px 24px;
    margin: 4px 0 10px;
    padding: 6px 0 8px;
    border-bottom: 1px solid #ccc;
    flex-wrap: wrap;
  }
  .official-report .invoice-bill-left,
  .official-report .invoice-bill-right {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0 6px;
    min-width: 0;
    line-height: 1.35;
  }
  .official-report .invoice-bill-right {
    margin-left: auto;
    text-align: right;
    justify-content: flex-end;
  }
  .official-report .invoice-bill-kicker {
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: var(--report-accent);
    white-space: nowrap;
  }
  .official-report .invoice-bill-sep {
    color: var(--report-accent);
    font-weight: 800;
    font-size: 0.85rem;
  }
  .official-report .invoice-bill-customer {
    font-size: 0.95rem;
    font-weight: 800;
    color: #1a1a1a;
    white-space: nowrap;
  }
  .official-report .invoice-bill-due {
    font-size: 0.95rem;
    font-weight: 800;
    color: #c0392b;
    white-space: nowrap;
  }
  .official-report .invoice-bill-phone {
    font-size: 0.82rem;
    color: #666;
    white-space: nowrap;
  }

  .official-report .invoice-lines-compact th {
    padding: 5px 6px;
    font-size: 0.68rem;
  }
  .official-report .invoice-lines-compact td {
    padding: 4px 6px;
    font-size: 0.8rem;
    line-height: 1.25;
  }
  .official-report .invoice-lines-compact td.text-left {
    padding-left: 8px;
    font-weight: 600;
  }

  .official-report .invoice-totals-block {
    display: flex;
    justify-content: flex-end;
    margin-top: 0;
    page-break-inside: avoid;
  }
  .official-report .invoice-totals-table {
    width: auto;
    min-width: 280px;
    border-top: none;
  }
  .official-report .invoice-totals-table th,
  .official-report .invoice-totals-table td {
    padding: 5px 10px;
    font-size: 0.8rem;
    border: 1px solid #333;
  }
  .official-report .invoice-totals-table th {
    background: #fff;
    font-weight: 700;
    text-align: right;
    text-transform: none;
    white-space: nowrap;
  }
  .official-report .invoice-totals-table td {
    text-align: right;
    min-width: 120px;
    font-weight: 700;
  }
  .official-report .invoice-totals-table .grand-total th,
  .official-report .invoice-totals-table .grand-total td {
    background: #fdecea;
    color: var(--report-accent);
    font-size: 0.9rem;
  }

  .official-report .invoice-signature-block {
    display: flex;
    justify-content: space-between;
    gap: 40px;
    margin-top: 40px;
    padding-top: 12px;
    page-break-inside: avoid;
    flex-wrap: wrap;
  }
  .official-report .invoice-sign-col {
    flex: 1 1 220px;
    max-width: 300px;
  }
  .official-report .invoice-sign-col-right {
    margin-left: auto;
    text-align: right;
  }
  .official-report .invoice-sign-label {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #666;
  }
  .official-report .invoice-mcharazo {
    margin: 2px 0 0;
    font-family: "Great Vibes", "Segoe Script", "Brush Script MT", cursive;
    font-size: 2.6rem;
    line-height: 1.1;
    color: #1a1a1a;
    min-height: 2.4rem;
  }
  .official-report .invoice-sign-space {
    min-height: 2.4rem;
  }
  .official-report .invoice-sign-line {
    margin-top: 2px;
    border-bottom: 1px solid #444;
    width: 100%;
    max-width: 240px;
  }
  .official-report .invoice-sign-col-right .invoice-sign-line {
    margin-left: auto;
  }
  .official-report .invoice-sign-caption {
    margin-top: 5px;
    font-size: 0.75rem;
    color: #555;
    font-weight: 600;
  }

  @media print {
    .official-report .invoice-bill-bar {
      display: flex !important;
      visibility: visible !important;
      align-items: baseline;
      flex-wrap: nowrap;
      margin: 2px 0 8px;
      padding: 4px 0 6px;
      border-bottom: 1px solid #333;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .official-report .invoice-bill-left,
    .official-report .invoice-bill-right {
      display: flex !important;
      flex-direction: row !important;
      align-items: baseline;
      flex-wrap: nowrap;
    }
    .official-report .invoice-bill-kicker,
    .official-report .invoice-bill-sep,
    .official-report .invoice-bill-due {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .official-report .invoice-mcharazo {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .official-report .invoice-lines thead {
      display: table-header-group;
    }
    .official-report .invoice-totals-block,
    .official-report .invoice-signature-block {
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .official-report .invoice-totals-table .grand-total th,
    .official-report .invoice-totals-table .grand-total td {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .official-report .official-stamp {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
  }
</style>
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('sales.partials.customer-picker-scripts')
@include('sales.partials.payment-modal-scripts')
@endsection
