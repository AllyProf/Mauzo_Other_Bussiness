@extends('layouts.app')

@section('title', 'Sale Receipt')

@section('content')
@include('partials.official-report-styles')

@php
    $business = Auth::user()->business;
    $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
    $totalAdjustments = $sale->items->sum(fn ($item) => (float) $item->discount_amount);
    $totalListAmount = $sale->items->sum(fn ($item) => (float) ($item->list_unit_price ?? $item->unit_price) * (float) $item->quantity);
    $paymentMethods = $sale->payments->pluck('payment_method')->unique()->filter();
    $paymentMethodLabel = $paymentMethods->count() > 1
        ? 'Split ('.$paymentMethods->map(fn ($m) => ucfirst(str_replace('_', ' ', $m)))->implode(' + ').')'
        : ($sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : 'N/A');
    $stampClass = match ($sale->payment_status) {
        'paid' => 'stamp-paid',
        'cancelled' => 'stamp-cancelled',
        default => 'stamp-pending',
    };
    $stampLabel = match ($sale->payment_status) {
        'paid' => 'PAID',
        'partial' => 'PARTIALLY PAID',
        'debt' => 'CREDIT / UNPAID',
        'pending' => 'UNPAID',
        'cancelled' => 'CANCELLED',
        default => strtoupper(str_replace('_', ' ', $sale->payment_status)),
    };
    $changeAmount = ($sale->payment_status === 'paid' && $sale->amount_paid > $sale->total_amount)
        ? (float) $sale->amount_paid - (float) $sale->total_amount
        : 0;
    $showListBreakdown = $totalAdjustments > 0 || abs($totalListAmount - (float) $sale->total_amount) > 0.001;
@endphp

<div class="official-report">
    <div class="app-title d-print-none">
        <div>
            <h1><i class="fa fa-file-text"></i> Sale Receipt #{{ $sale->reference_no }}</h1>
            <p>Transaction details and printable customer receipt.</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ url('/home') }}">{{ __('menu.dashboard') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">Invoices</a></li>
            <li class="breadcrumb-item active">Receipt #{{ $sale->reference_no }}</li>
        </ul>
        <div class="mt-2">
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i> Invoices</a>
            @if($sale->payment_status !== 'cancelled')
                <a href="{{ route('invoices.show', $sale) }}" class="btn btn-primary btn-sm"><i class="fa fa-file-text-o"></i> View Invoice</a>
            @endif
        </div>
    </div>

    @if($sale->payment_status === 'paid')
    <div class="alert alert-success d-print-none">
        <i class="fa fa-check-circle"></i> Payment complete. Use <strong>Print Receipt</strong> below and give it to the customer.
    </div>
    @endif

    <div class="tile report-sheet">
        <div class="report-header-center">
            @if($business->logo_path)
                <img src="{{ asset('storage/'.$business->logo_path) }}" alt="{{ $business->name }}">
            @endif
            <h1>{{ $business->name }}</h1>
            <div class="biz-contact-info">
                @if($business->address){{ $business->address }}@endif
                @if($business->phone) | Mobile: {{ $business->phone }}@endif
                @if($business->email) | Email: {{ $business->email }}@endif
                @if($business->tin_number) | TIN: {{ $business->tin_number }}@endif
            </div>
            <div class="operations-title">Sale Receipt</div>
            <hr class="accent-divider">
        </div>

        <div class="report-sub-meta">
            <span>Cashier: {{ $sale->user->name }}</span>
            <span>Ref: {{ $sale->reference_no }}</span>
            <span>Date: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</span>
        </div>

        <div class="title-area">
            <h2 class="main-report-title">Receipt #{{ $sale->reference_no }}</h2>
            <div class="official-stamp {{ $stampClass }}">{{ $stampLabel }}</div>
        </div>

        <div class="text-center mb-3 d-print-none">
            <button type="button" onclick="window.print()" class="btn btn-print shadow-sm">
                <i class="fa fa-print"></i> Print Receipt / PDF
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
                <span class="invoice-bill-phone">· {{ $paymentMethodLabel }}</span>
            </div>
            @if($balanceDue > 0 && ! in_array($sale->payment_status, ['cancelled'], true))
            <div class="invoice-bill-right">
                <span class="invoice-bill-kicker">Balance Due</span>
                <span class="invoice-bill-sep">:</span>
                <strong class="invoice-bill-due">{{ money($balanceDue) }}</strong>
                @if($sale->due_date)
                    <span class="invoice-bill-phone">· {{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</span>
                @endif
            </div>
            @elseif($changeAmount > 0)
            <div class="invoice-bill-right">
                <span class="invoice-bill-kicker">Change</span>
                <span class="invoice-bill-sep">:</span>
                <strong class="invoice-bill-customer">{{ money($changeAmount) }}</strong>
            </div>
            @endif
        </div>

        <div class="table-responsive">
            <table class="report-table mb-0 invoice-lines invoice-lines-compact">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th class="text-left">{{ __('tables.columns.item_name') }}</th>
                        <th style="width:70px;">Qty</th>
                        <th style="width:110px;">List Price</th>
                        <th style="width:110px;">Unit Price</th>
                        <th style="width:120px;">Adjustment</th>
                        <th style="width:120px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->items as $index => $item)
                        @php
                            $listPrice = (float) ($item->list_unit_price ?? $item->unit_price);
                            $hasCustomPrice = $item->adjustment_mode === 'price' && abs((float) $item->unit_price - $listPrice) > 0.001;
                            $hasDiscount = $item->adjustment_mode === 'discount' && (float) $item->discount_amount > 0;
                        @endphp
                        <tr>
                            <td class="text-muted-row">{{ $index + 1 }}</td>
                            <td class="text-left">
                                @if($item->service_id)
                                    {{ $item->line_description ?: $item->service?->name ?? 'Service' }}
                                @else
                                    {{ $item->item->name ?? 'Item' }}
                                    @if($item->itemPackaging?->packagingType?->name)
                                        <span class="text-muted font-weight-normal"> · {{ $item->itemPackaging->packagingType->name }}</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                {{ $item->quantity }}
                                @if(! $item->service_id && $item->itemPackaging?->packagingType?->name)
                                    <span class="text-muted"> {{ $item->itemPackaging->packagingType->name }}</span>
                                @endif
                            </td>
                            <td>{{ money($listPrice) }}</td>
                            <td>{{ money($item->unit_price) }}</td>
                            <td>
                                @if($hasDiscount)
                                    <span class="text-success">
                                        Discount
                                        @if($item->discount_type === 'percent')
                                            ({{ rtrim(rtrim(number_format((float) $item->discount_value, 2), '0'), '.') }}%)
                                        @else
                                            ({{ money($item->discount_amount) }} off)
                                        @endif
                                    </span>
                                @elseif($hasCustomPrice)
                                    <span class="text-info">Custom price</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="amount-accent">{{ money($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="invoice-totals-block">
            <table class="report-table invoice-totals-table mb-0">
                <tbody>
                    @if($showListBreakdown)
                    <tr>
                        <th class="text-right">List Total</th>
                        <td class="text-right">{{ money($totalListAmount) }}</td>
                    </tr>
                    @if($totalAdjustments > 0)
                    <tr>
                        <th class="text-right text-success">Total Discount</th>
                        <td class="text-right text-success">- {{ money($totalAdjustments) }}</td>
                    </tr>
                    @elseif($totalListAmount > (float) $sale->total_amount)
                    <tr>
                        <th class="text-right text-info">Price Adjustment</th>
                        <td class="text-right text-info">- {{ money($totalListAmount - (float) $sale->total_amount) }}</td>
                    </tr>
                    @endif
                    @endif
                    <tr class="grand-total">
                        <th class="text-right">Total (TZS)</th>
                        <td class="text-right amount-accent">{{ money($sale->total_amount) }}</td>
                    </tr>
                    @if($balanceDue > 0 && ! in_array($sale->payment_status, ['cancelled'], true))
                    <tr>
                        <th class="text-right text-danger">Balance Due</th>
                        <td class="text-right text-danger">{{ money($balanceDue) }}</td>
                    </tr>
                    @endif
                    @if($changeAmount > 0)
                    <tr>
                        <th class="text-right">Change</th>
                        <td class="text-right">{{ money($changeAmount) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($sale->notes)
        <div class="mt-3">
            <div class="stats-card-title mb-2">Notes</div>
            <p class="mb-0">{{ $sale->notes }}</p>
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
                            <th>Provider / Ref</th>
                            <th>{{ __('tables.columns.cashier') }}</th>
                            <th>{{ __('tables.columns.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->payments as $payment)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d M Y H:i') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                            <td>{{ $payment->payment_provider ?? '—' }} {{ $payment->transaction_reference ? '('.$payment->transaction_reference.')' : '' }}</td>
                            <td>{{ $payment->user->name ?? '—' }}</td>
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
                <div class="invoice-mcharazo">{{ explode(' ', trim($business->name ?: 'Store'))[0] }}</div>
                <div class="invoice-sign-line"></div>
                <div class="invoice-sign-caption">Authorized Signature · {{ $sale->user->name }}</div>
            </div>
            <div class="invoice-sign-col invoice-sign-col-right">
                <div class="invoice-sign-label">Received By</div>
                <div class="invoice-sign-space"></div>
                <div class="invoice-sign-line"></div>
                <div class="invoice-sign-caption">Customer Signature / Stamp</div>
            </div>
        </div>

        <div class="text-center mt-4 small text-muted">
            Generated {{ now()->format('d M Y, H:i') }} · Thank you for your business.<br>
            Powered By <strong>EmCa Technologies</strong> — <a href="https://www.emca.tech" target="_blank" rel="noopener">www.emca.tech</a>
        </div>
    </div>
</div>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">

<style>
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
    font-size: 1.2rem;
    line-height: 1.15;
    color: #1a1a1a;
    min-height: 1.35rem;
  }
  .official-report .invoice-sign-space { min-height: 2.4rem; }
  .official-report .invoice-sign-line {
    margin-top: 2px;
    border-bottom: 1px solid #444;
    width: 100%;
    max-width: 240px;
  }
  .official-report .invoice-sign-col-right .invoice-sign-line { margin-left: auto; }
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
    .official-report .invoice-bill-due,
    .official-report .invoice-mcharazo,
    .official-report .invoice-totals-table .grand-total th,
    .official-report .invoice-totals-table .grand-total td {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .official-report .invoice-lines thead { display: table-header-group; }
    .official-report .invoice-totals-block,
    .official-report .invoice-signature-block {
      page-break-inside: avoid;
      break-inside: avoid;
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
