<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice {{ $sale->reference_no }}</title>
  <style>
    body { font-family: Arial, sans-serif; color: #333; margin: 24px; }
    .invoice-brand { color: #940000; font-weight: 700; margin: 0 0 4px; }
    .invoice-title { color: #940000; font-size: 28px; font-weight: 800; letter-spacing: 2px; margin: 0 0 12px; text-align: right; }
    .invoice-meta-table { margin-left: auto; border-collapse: collapse; }
    .invoice-meta-table td { padding: 2px 0; }
    .text-muted { color: #666; }
    .text-right { text-align: right; }
    .mb-4 { margin-bottom: 24px; }
    .mb-2 { margin-bottom: 8px; }
    .mb-0 { margin-bottom: 0; }
    .row { width: 100%; }
    .col-7 { width: 58%; float: left; }
    .col-5 { width: 42%; float: right; }
    .col-md-6 { width: 50%; float: left; }
    .clearfix { clear: both; }
    table { width: 100%; border-collapse: collapse; }
    .invoice-lines th, .invoice-lines td { border: 1px solid #dee2e6; padding: 8px 10px; }
    .invoice-lines thead th { background: #940000; color: #fff; }
    .invoice-grand-total th { background: #f8f9fa; font-size: 16px; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; color: #fff; }
    .badge-success { background: #28a745; }
    .badge-info { background: #17a2b8; }
    .badge-danger { background: #dc3545; }
    .badge-secondary { background: #6c757d; }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }
    .text-uppercase { text-transform: uppercase; }
    .small { font-size: 12px; }
    .border-top { border-top: 1px solid #dee2e6; padding-top: 16px; margin-top: 24px; }
    .text-center { text-align: center; }
  </style>
</head>
<body>
  <div class="invoice-header row mb-4">
    <div class="col-7">
      <h2 class="invoice-brand">{{ $business->name }}</h2>
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
    <div class="col-5">
      <h1 class="invoice-title">INVOICE</h1>
      <table class="invoice-meta-table">
        <tr><td class="text-muted">Invoice No.</td><td class="text-right"><strong>{{ $sale->reference_no }}</strong></td></tr>
        <tr><td class="text-muted">Date</td><td class="text-right">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td></tr>
        <tr><td class="text-muted">Prepared by</td><td class="text-right">{{ $sale->user->name ?? 'Staff' }}</td></tr>
        <tr>
          <td class="text-muted">Status</td>
          <td class="text-right"><span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span></td>
        </tr>
      </table>
    </div>
  </div>
  <div class="clearfix"></div>

  <div class="row mb-4">
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
    <div class="col-md-6 text-right">
      <h6 class="text-uppercase text-muted mb-2">Payment Due</h6>
      <p class="mb-0"><strong>{{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</strong></p>
    </div>
    @endif
  </div>

  <table class="invoice-lines">
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
          <td>{{ $line->service_id ? '—' : ($line->item->sku ?? '—') }}</td>
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

  @if($sale->notes)
  <div class="mb-4">
    <h6 class="text-uppercase text-muted">Notes</h6>
    <p class="mb-0">{!! nl2br(e($sale->notes)) !!}</p>
  </div>
  @endif

  @if(($paymentReceiveDetails ?? collect())->isNotEmpty())
  <div class="mb-4">
    <h6 class="text-uppercase text-muted">Payment Details</h6>
    <table class="invoice-lines">
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
  @endif

  <div class="border-top text-center text-muted small">
    Thank you for your business.
  </div>
</body>
</html>
