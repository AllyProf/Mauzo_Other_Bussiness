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
    .bill-to-table { margin: 8px 0 20px; }
    .bill-to-table td { vertical-align: top; padding: 0; line-height: 1.5; }
    .section-label { font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #666; margin-bottom: 6px; }
  </style>
</head>
<body>
  @include('invoices.partials.header-brand', [
    'sale' => $sale,
    'business' => $business,
    'branch' => $branch,
    'statusClass' => $statusClass,
    'statusLabel' => $statusLabel,
    'logoDataUri' => $logoDataUri ?? null,
    'metaAlign' => 'right',
    'metaTableClass' => 'invoice-meta-table',
  ])
  <div class="clearfix"></div>

  <table class="bill-to-table">
    <tr>
      <td style="width: 60%;">
        <div class="section-label">Bill To</div>
        @if($sale->customer_name)
          <strong>{{ $sale->customer_name }}</strong><br>
          @if($sale->customer_phone)Phone: {{ $sale->customer_phone }}<br>@endif
          @if($sale->customer && $sale->customer->email)Email: {{ $sale->customer->email }}<br>@endif
        @else
          <span class="text-muted">Walk-in Customer</span>
        @endif
      </td>
      <td style="width: 40%;" class="text-right">
        @if($sale->due_date && $balanceDue > 0)
          <div class="section-label">Payment Due</div>
          <strong>{{ \Carbon\Carbon::parse($sale->due_date)->format('d M Y') }}</strong>
        @endif
      </td>
    </tr>
  </table>

  <table class="invoice-lines">
    <thead>
      <tr>
        <th>#</th>
        <th>{{ __('tables.columns.description') }}</th>
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
              @if($line->itemPackaging?->packagingType?->name)
                <br><span class="small text-muted">Sold as: {{ $line->itemPackaging->packagingType->name }}</span>
              @endif
            @endif
          </td>
          <td class="text-right">
            {{ number_format((float) $line->quantity, 0) }}
            @if(! $line->service_id && $line->itemPackaging?->packagingType?->name)
              <br><span class="small text-muted">{{ $line->itemPackaging->packagingType->name }}</span>
            @endif
          </td>
          <td class="text-right">{{ money($line->unit_price) }}</td>
          <td class="text-right">{{ money($line->subtotal) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      @include('invoices.partials.totals-footer', [
        'sale' => $sale,
        'business' => $business,
        'balanceDue' => $balanceDue,
        'vatBreakdown' => $vatBreakdown ?? null,
      ])
    </tfoot>
  </table>

  @if($sale->notes)
  <div class="mb-4">
    <h6 class="text-uppercase text-muted">Notes</h6>
    <p class="mb-0">{!! nl2br(e($sale->notes)) !!}</p>
  </div>
  @endif

  @php $invoiceSettings = $business->invoiceSettings(); @endphp
  @if($invoiceSettings['terms'] !== '')
  <div class="mb-4">
    <h6 class="text-uppercase text-muted">Terms &amp; Conditions</h6>
    <p class="mb-0 small">{!! nl2br(e($invoiceSettings['terms'])) !!}</p>
  </div>
  @endif

  @if($invoiceSettings['show_payment_details'] && ($paymentReceiveDetails ?? collect())->isNotEmpty())
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

  @if($invoiceSettings['show_signature'])
  <table style="margin-top: 36px;">
    <tr>
      <td style="width: 45%; vertical-align: bottom;">
        <div class="section-label">For {{ $business->name }}</div>
        <div style="height: 36px;"></div>
        <div style="border-top: 1px solid #999; padding-top: 4px;" class="small text-muted">Authorized Signature</div>
      </td>
      <td style="width: 10%;"></td>
      <td style="width: 45%; vertical-align: bottom;">
        <div class="section-label">Received By</div>
        <div style="height: 36px;"></div>
        <div style="border-top: 1px solid #999; padding-top: 4px;" class="small text-muted">Customer Signature / Stamp</div>
      </td>
    </tr>
  </table>
  @endif

  <div class="border-top text-center text-muted small">
    @if($invoiceSettings['footer_message'] !== ''){{ $invoiceSettings['footer_message'] }}<br>@endif
    Powered By <strong>EmCa Technologies</strong> — <a href="https://www.emca.tech" style="color:#940000;">www.emca.tech</a>
  </div>
</body>
</html>
