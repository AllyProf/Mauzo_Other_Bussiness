@php
  $logoSrc = $logoDataUri ?? $business->logoUrl();
  $metaAlign = $metaAlign ?? 'right';
  $metaTableClass = $metaTableClass ?? 'invoice-meta-table ml-auto';
  $invoiceSettings = $business->invoiceSettings();
@endphp
<table class="invoice-header" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
  <tr>
    <td style="width: 58%; vertical-align: top; padding: 0;">
      @if($logoSrc)
        <img src="{{ $logoSrc }}" alt="{{ $business->name }} logo" class="invoice-logo" style="max-height:72px;max-width:220px;margin-bottom:6px;">
      @endif
      <h2 class="invoice-brand">{{ $business->name }}</h2>
      @if($branch)
        <div class="text-muted">{{ $branch->name }}</div>
      @endif
      @if($business->address)<div>{{ $business->address }}</div>@endif
      @if($business->phone)<div>Tel: {{ $business->phone }}</div>@endif
      @if($business->email)<div>{{ $business->email }}</div>@endif
      @if($business->contact_person)<div>Contact: {{ $business->contact_person }}</div>@endif
      @if($business->tin_number)<div><strong>TIN:</strong> {{ $business->tin_number }}</div>@endif
      @if($business->vat_number)<div><strong>VAT No.:</strong> {{ $business->vat_number }}</div>@endif
    </td>
    <td style="width: 42%; vertical-align: top; padding: 0; text-align: {{ $metaAlign }};">
      <h1 class="invoice-title">{{ mb_strtoupper($invoiceSettings['title'] ?: 'INVOICE') }}</h1>
      <table class="{{ $metaTableClass }}" style="width: auto; margin-left: auto; border-collapse: collapse;">
        <tr><td class="text-muted" style="padding: 2px 12px 2px 0; text-align: left;">Invoice No.</td><td style="text-align: left;"><strong>{{ $sale->reference_no }}</strong></td></tr>
        <tr><td class="text-muted" style="padding: 2px 12px 2px 0; text-align: left;">Date</td><td style="text-align: left;">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td></tr>
        @if($invoiceSettings['show_prepared_by'])
        <tr><td class="text-muted" style="padding: 2px 12px 2px 0; text-align: left;">Prepared by</td><td style="text-align: left;">{{ $sale->user->name ?? 'Staff' }}</td></tr>
        @endif
        <tr>
          <td class="text-muted" style="padding: 2px 12px 2px 0; text-align: left;">Status</td>
          <td style="text-align: left;"><span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span></td>
        </tr>
      </table>
    </td>
  </tr>
</table>
