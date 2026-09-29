@php
  $money = fn ($value) => number_format((float) $value, 0).'/=';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
  @page { margin: 36px 42px; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111; font-size: 11px; line-height: 1.35; margin: 0; }
  table { border-collapse: collapse; width: 100%; }
  td { vertical-align: top; }
  .title { font-family: 'DejaVu Serif', Georgia, serif; font-size: 34px; font-weight: bold; color: #940000; letter-spacing: 1px; margin: 6px 0 4px; }
  .title-bar { height: 4px; background: #940000; width: 90%; margin-bottom: 14px; }
  .company-name { font-weight: bold; font-size: 11.5px; }
  .muted-label { font-weight: bold; }
  .meta { text-align: right; font-size: 10.5px; }
  .meta td { padding: 3px 0; }
  .meta .label { font-weight: bold; padding-right: 8px; }
  .remarks { text-align: right; font-size: 11px; }
  .remarks .heading { font-weight: bold; margin-bottom: 6px; }
  .remarks .accent { color: #940000; font-weight: bold; }
  .section-label { font-weight: bold; letter-spacing: .5px; }
  .purpose { margin: 34px 0 26px; }
  .items th { font-size: 11px; font-weight: bold; padding: 4px 6px; border-bottom: 1px solid #3a9ad9; }
  .items td { padding: 4px 6px; }
  .items .row td { background: #c5dff5; border: 1px solid #3a9ad9; }
  .items .num { text-align: right; }
  .items .center { text-align: center; }
  .totals td { padding: 4px 6px; }
  .totals .label { text-align: right; font-weight: bold; }
  .totals .value { text-align: right; font-weight: bold; width: 92px; }
  .totals .vat .label { font-weight: normal; font-size: 10px; letter-spacing: .5px; }
  .totals .vat .value { background: #c5dff5; border: 1px solid #3a9ad9; font-weight: normal; }
  .totals .grand td { border-top: 1px solid #3a9ad9; border-bottom: 1px solid #3a9ad9; }
  .footer { margin-top: 26px; font-size: 11px; }
</style>
</head>
<body>

<table>
  <tr>
    <td style="width: 55%;">
      <div class="title">INVOICE</div>
      <div class="title-bar"></div>
    </td>
    <td style="width: 45%; text-align: right;">
      @if($company['logo'])
        <img src="{{ $company['logo'] }}" alt="Logo" style="max-height: 90px; max-width: 230px;">
      @else
        <div style="font-size: 26px; font-weight: bold; color: #940000; margin-top: 10px;">{{ $company['name'] ?: $platform }}</div>
      @endif
    </td>
  </tr>
</table>

<table>
  <tr>
    <td style="width: 55%;">
      @if($company['name'])<div class="company-name">{{ $company['name'] }}</div>@endif
      @foreach($company['address_lines'] as $line)<div>{{ $line }}</div>@endforeach
      @if($company['phone'])<div><span class="muted-label">Phone:</span> {{ $company['phone'] }}</div>@endif
      @if($company['tin'])<div><span class="muted-label">TIN:</span> {{ $company['tin'] }}</div>@endif
      @if($company['website'])<div>Web: <strong>{{ $company['website'] }}</strong></div>@endif
    </td>
    <td style="width: 45%;">
      <table class="meta" style="width: auto; margin-left: auto; margin-top: 18px;">
        <tr><td class="label">Invoice#:</td><td>{{ $invoice->invoice_number }}</td></tr>
        <tr><td class="label">Date:</td><td>{{ $invoiceDate }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<table style="margin-top: 16px;">
  <tr>
    <td style="width: 50%; padding-top: 22px;">
      <div class="section-label">INVOICE TO:</div>
      @foreach($recipientLines as $line)<div>{{ $line }}</div>@endforeach
      @if($recipientRegion)<div><strong>{{ $recipientRegion }}</strong></div>@endif
    </td>
    <td style="width: 50%;" class="remarks">
      <div class="heading">Remarks / Payment Instructions:</div>
      @if($payment['intro'])<div style="margin-bottom: 8px;">{{ $payment['intro'] }}</div>@endif
      @foreach($payment['methods'] as $method)
        <div style="{{ $loop->last ? '' : 'margin-bottom: 8px;' }}">
          @if($method['provider'])<div style="font-weight: bold;">{{ $method['provider'] }}</div>@endif
          @if($method['account_name'])<div class="accent">{{ $method['account_name'] }}</div>@endif
          @if($method['number'])<div class="accent">{{ trim($method['number_label'].' '.$method['number']) }}</div>@endif
        </div>
      @endforeach
    </td>
  </tr>
</table>

@if($purpose)
<div class="purpose">Purpose: <strong>{{ $purpose }}</strong></div>
@else
<div class="purpose"></div>
@endif

<table class="items">
  <thead>
    <tr>
      <th style="width: 24px;" class="center">#</th>
      <th class="center">Description</th>
      <th style="width: 60px;" class="center">Qnty</th>
      <th style="width: 92px;" class="center">Unit Price</th>
      <th style="width: 92px;" class="center">Total Price</th>
    </tr>
  </thead>
  <tbody>
    @foreach($lines as $i => $line)
    <tr class="row">
      <td class="center">{{ $i + 1 }}</td>
      <td>{{ $line['description'] }}</td>
      <td class="num">{{ number_format($line['qty']) }}</td>
      <td class="num">{{ $money($line['unit_price']) }}</td>
      <td class="num">{{ $money($line['total']) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<table class="totals">
  <tr>
    <td></td>
    <td class="label" style="width: 140px;">Subtotal</td>
    <td class="value">{{ $money($subtotal) }}</td>
  </tr>
  <tr class="vat">
    <td></td>
    <td class="label">VAT {{ rtrim(rtrim(number_format($vatPercent, 2), '0'), '.') }}%</td>
    <td class="value">{{ $vatAmount > 0 ? $money($vatAmount) : '0.0/=' }}</td>
  </tr>
  <tr class="grand">
    <td style="border: 0;"></td>
    <td class="label">Total Price</td>
    <td class="value">{{ $money($total) }}</td>
  </tr>
</table>

@if(count($footerLines))
<div class="footer">
  @foreach($footerLines as $line)<div>{{ $line }}</div>@endforeach
</div>
@endif

</body>
</html>
