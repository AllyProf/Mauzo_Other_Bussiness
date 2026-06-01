@extends('layouts.app')

@section('title', 'Sale Receipt')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-file-text"></i> Sale Receipt #{{ $sale->reference_no }}</h1>
    <p>Transaction Details</p>
  </div>
  <div>
    <a href="{{ route('invoices.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Invoices</a>
    @if($sale->payment_status !== 'cancelled')
      <a href="{{ route('invoices.show', $sale) }}" class="btn btn-primary"><i class="fa fa-file-text-o"></i> View Invoice</a>
    @endif
  </div>
</div>

@if($sale->payment_status === 'paid')
<div class="alert alert-success">
  <i class="fa fa-check-circle"></i> Payment complete. Use <strong>Print Receipt</strong> below and give it to the customer.
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="row mb-4">
        <div class="col-6">
          <h2 class="page-header"><i class="fa fa-shopping-cart"></i> Sale Receipt</h2>
        </div>
        <div class="col-6">
          <h5 class="text-right">Date: {{ \Carbon\Carbon::parse($sale->sale_date)->format('M d, Y') }}</h5>
        </div>
      </div>
      <div class="row invoice-info">
        <div class="col-4">
          Business:
          <address>
            <strong>{{ Auth::user()->business->name }}</strong><br>
            {{ Auth::user()->business->address ?? '' }}<br>
            TIN: {{ Auth::user()->business->tin_number ?? 'N/A' }}
          </address>
        </div>
        <div class="col-4">
          Cashier:
          <address>
            <strong>{{ $sale->user->name }}</strong>
          </address>
        </div>
        <div class="col-4">
          <b>Reference:</b> {{ $sale->reference_no }}<br>
          <b>Payment Method:</b> {{ $sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : 'N/A' }}<br>
          @if($sale->customer_name)
          <b>Customer:</b> {{ $sale->customer_name }}@if($sale->customer_phone) ({{ $sale->customer_phone }})@endif<br>
          @endif
          @if($sale->notes)
          <b>Notes:</b><br>
          <span class="text-muted">{!! nl2br(e($sale->notes)) !!}</span>
          @endif
        </div>
      </div>
      <div class="row mt-4">
        <div class="col-12 table-responsive">
          <table class="table table-striped table-bordered">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>List Price</th>
                <th>Unit Price</th>
                <th>Adjustment</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sale->items as $item)
                @php
                  $listPrice = (float) ($item->list_unit_price ?? $item->unit_price);
                  $hasCustomPrice = $item->adjustment_mode === 'price' && abs((float) $item->unit_price - $listPrice) > 0.001;
                  $hasDiscount = $item->adjustment_mode === 'discount' && (float) $item->discount_amount > 0;
                @endphp
                <tr>
                  <td>{{ $item->item->name }} ({{ $item->item->sku }})</td>
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
                  <td>{{ money($item->subtotal) }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              @php
                $totalAdjustments = $sale->items->sum(fn ($item) => (float) $item->discount_amount);
                $totalListAmount = $sale->items->sum(fn ($item) => (float) ($item->list_unit_price ?? $item->unit_price) * (float) $item->quantity);
              @endphp
              @if($totalAdjustments > 0 || abs($totalListAmount - (float) $sale->total_amount) > 0.001)
              <tr>
                <th colspan="5" class="text-right">List Total:</th>
                <th>{{ money($totalListAmount) }}</th>
              </tr>
              @if($totalAdjustments > 0)
              <tr>
                <th colspan="5" class="text-right text-success">Total Discount:</th>
                <th class="text-success">- {{ money($totalAdjustments) }}</th>
              </tr>
              @elseif($totalListAmount > (float) $sale->total_amount)
              <tr>
                <th colspan="5" class="text-right text-info">Price Adjustment:</th>
                <th class="text-info">- {{ money($totalListAmount - (float) $sale->total_amount) }}</th>
              </tr>
              @endif
              @endif
              <tr>
                <th colspan="5" class="text-right">Grand Total:</th>
                <th>{{ money($sale->total_amount) }}</th>
              </tr>
              <tr>
                <th colspan="5" class="text-right">Amount Paid:</th>
                <th>{{ money($sale->amount_paid) }}</th>
              </tr>
              @php $balanceDue = max(0, $sale->total_amount - $sale->amount_paid); @endphp
              @if($balanceDue > 0 && !in_array($sale->payment_status, ['cancelled']))
              <tr>
                <th colspan="5" class="text-right text-danger">Balance Due:</th>
                <th class="text-danger">{{ money($balanceDue) }}</th>
              </tr>
              @if($sale->due_date)
              <tr>
                <th colspan="5" class="text-right">Repayment Due:</th>
                <th>{{ \Carbon\Carbon::parse($sale->due_date)->format('M d, Y') }}</th>
              </tr>
              @endif
              @endif
              @if($sale->payment_status === 'paid' && $sale->amount_paid > $sale->total_amount)
              <tr>
                <th colspan="5" class="text-right">Change:</th>
                <th>{{ money($sale->amount_paid - $sale->total_amount) }}</th>
              </tr>
              @endif
            </tfoot>
          </table>
        </div>
      </div>

      @if($sale->payments->count() > 0)
      <div class="row mt-4">
        <div class="col-12 table-responsive">
          <h5>Payment History</h5>
          <table class="table table-sm table-bordered">
            <thead class="bg-light">
              <tr>
                <th>Date</th>
                <th>Method</th>
                <th>Provider/Ref</th>
                <th>Cashier</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sale->payments as $payment)
                <tr>
                  <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y H:i') }}</td>
                  <td>{{ ucfirst($payment->payment_method) }}</td>
                  <td>{{ $payment->payment_provider ?? '-' }} {{ $payment->transaction_reference ? '('.$payment->transaction_reference.')' : '' }}</td>
                  <td>{{ $payment->user->name ?? '-' }}</td>
                  <td>{{ money($payment->amount) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      <div class="row d-print-none mt-2">
        <div class="col-12 text-right">
          <button class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Print Receipt</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  @media print {
    .app-title, .d-print-none, .app-sidebar, .app-header {
      display: none !important;
    }
    .app-content {
      margin-left: 0 !important;
      margin-top: 0 !important;
    }
  }
</style>
@endsection
