@extends('layouts.app')

@section('title', 'Sale Receipt')

@section('content')
@include('partials.official-report-styles')

@php
    $business = Auth::user()->business;
    $logoUrl = $business->logo_path
        ? asset('storage/'.$business->logo_path)
        : 'https://ui-avatars.com/api/?name='.urlencode($business->name).'&background=940000&color=fff&size=120';
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
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
        default => 'Official',
    };
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
            <img src="{{ $logoUrl }}" alt="{{ $business->name }}">
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

        <div class="text-center mb-4 d-print-none">
            <button type="button" onclick="window.print()" class="btn btn-print shadow-sm">
                <i class="fa fa-print"></i> Print Receipt / PDF
            </button>
            <div class="mt-2 text-muted" style="font-size:0.85rem;">
                <i class="fa fa-info-circle"></i> Use your browser print dialog to save as PDF or print for the customer.
            </div>
        </div>

        <div class="report-stats-grid">
            <div>
                <div class="stats-card-title">Transaction Information</div>
                <div class="stats-row"><strong>Reference:</strong> <span>{{ $sale->reference_no }}</span></div>
                <div class="stats-row"><strong>Date:</strong> <span>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</span></div>
                <div class="stats-row"><strong>Cashier:</strong> <span>{{ $sale->user->name }}</span></div>
                @if($sale->customer_name)
                <div class="stats-row"><strong>Customer:</strong> <span>{{ $sale->customer_name }}@if($sale->customer_phone) ({{ $sale->customer_phone }})@endif</span></div>
                @endif
                @if($sale->notes)
                <div class="stats-row"><strong>Notes:</strong> <span>{{ $sale->notes }}</span></div>
                @endif
            </div>
            <div>
                <div class="stats-card-title">Payment Summary</div>
                <div class="stats-row"><strong>Status:</strong> <span>{{ ucfirst(str_replace('_', ' ', $sale->payment_status)) }}</span></div>
                <div class="stats-row"><strong>Method:</strong> <span>{{ $paymentMethodLabel }}</span></div>
                <div class="stats-row"><strong>Grand Total:</strong> <span class="amount-accent">{{ money($sale->total_amount) }}</span></div>
                <div class="stats-row"><strong>Amount Paid:</strong> <span>{{ money($sale->amount_paid) }}</span></div>
                @if($balanceDue > 0 && ! in_array($sale->payment_status, ['cancelled']))
                <div class="stats-row"><strong>Balance Due:</strong> <span class="text-danger font-weight-bold">{{ money($balanceDue) }}</span></div>
                @if($sale->due_date)
                <div class="stats-row"><strong>Due Date:</strong> <span>{{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</span></div>
                @endif
                @endif
                @if($sale->payment_status === 'paid' && $sale->amount_paid > $sale->total_amount)
                <div class="stats-row"><strong>Change:</strong> <span>{{ money($sale->amount_paid - $sale->total_amount) }}</span></div>
                @endif
            </div>
        </div>

        <div class="stats-card-title mb-2">Items Purchased</div>
        <div class="table-responsive">
            <table class="report-table mb-0">
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
                                    @if($item->item?->sku)
                                        <br><small class="text-muted font-weight-normal">SKU: {{ $item->item->sku }}</small>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
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
                <tfoot>
                    @if($totalAdjustments > 0 || abs($totalListAmount - (float) $sale->total_amount) > 0.001)
                    <tr>
                        <th colspan="6">List Total</th>
                        <th>{{ money($totalListAmount) }}</th>
                    </tr>
                    @if($totalAdjustments > 0)
                    <tr>
                        <th colspan="6" class="text-success">Total Discount</th>
                        <th class="text-success">- {{ money($totalAdjustments) }}</th>
                    </tr>
                    @elseif($totalListAmount > (float) $sale->total_amount)
                    <tr>
                        <th colspan="6" class="text-info">Price Adjustment</th>
                        <th class="text-info">- {{ money($totalListAmount - (float) $sale->total_amount) }}</th>
                    </tr>
                    @endif
                    @endif
                    <tr class="grand-total">
                        <th colspan="6">Grand Total</th>
                        <th>{{ money($sale->total_amount) }}</th>
                    </tr>
                    <tr>
                        <th colspan="6">Amount Paid</th>
                        <th>{{ money($sale->amount_paid) }}</th>
                    </tr>
                    @if($balanceDue > 0 && ! in_array($sale->payment_status, ['cancelled']))
                    <tr>
                        <th colspan="6" class="text-danger">Balance Due</th>
                        <th class="text-danger">{{ money($balanceDue) }}</th>
                    </tr>
                    @endif
                    @if($sale->payment_status === 'paid' && $sale->amount_paid > $sale->total_amount)
                    <tr>
                        <th colspan="6">Change</th>
                        <th>{{ money($sale->amount_paid - $sale->total_amount) }}</th>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>

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

        <div class="mt-4 pt-4 border-top row">
            <div class="col-md-6">
                <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Cashier signature</small>
                <div class="mt-2 font-weight-bold" style="font-size:1.05rem; color: var(--report-accent);">{{ $sale->user->name }}</div>
                <div class="mt-2 text-muted">_______________________________________</div>
            </div>
            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                <small class="font-weight-bold text-uppercase" style="letter-spacing:1px;">Customer copy</small>
                <div class="mt-3 text-muted">_______________________________________</div>
            </div>
        </div>

        <div class="text-center mt-4 small text-muted">
            Generated {{ now()->format('d M Y, H:i') }} · Thank you for your business.
        </div>
    </div>
</div>
@endsection
