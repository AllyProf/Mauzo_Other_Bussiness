@php
  $logoSrc = $logoDataUri ?? $business->logoUrl();
  $metaAlign = $metaAlign ?? 'right';
  $metaTableClass = $metaTableClass ?? 'invoice-meta-table ml-auto';
@endphp
<div class="invoice-header row mb-4">
  <div class="col-7">
    @if($logoSrc)
      <img src="{{ $logoSrc }}" alt="{{ $business->name }} logo" class="invoice-logo mb-2" style="max-height:72px;max-width:220px;object-fit:contain;">
    @endif
    <h2 class="invoice-brand mb-1">{{ $business->name }}</h2>
    @if($branch)
      <div class="text-muted">{{ $branch->name }}</div>
    @endif
    @if($business->address)<div>{{ $business->address }}</div>@endif
    @if($business->phone)<div>Tel: {{ $business->phone }}</div>@endif
    @if($business->email)<div>{{ $business->email }}</div>@endif
    @if($business->contact_person)<div>Contact: {{ $business->contact_person }}</div>@endif
    @if($business->tin_number)<div><strong>TIN:</strong> {{ $business->tin_number }}</div>@endif
    @if($business->vat_number)<div><strong>VAT No.:</strong> {{ $business->vat_number }}</div>@endif
  </div>
  <div class="col-5 text-{{ $metaAlign }}">
    <h1 class="invoice-title">INVOICE</h1>
    <table class="{{ $metaTableClass }}">
      <tr><td class="text-muted pr-3">Invoice No.</td><td><strong>{{ $sale->reference_no }}</strong></td></tr>
      <tr><td class="text-muted pr-3">Date</td><td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</td></tr>
      <tr><td class="text-muted pr-3">Prepared by</td><td>{{ $sale->user->name ?? 'Staff' }}</td></tr>
      <tr>
        <td class="text-muted pr-3">Status</td>
        <td><span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span></td>
      </tr>
    </table>
  </div>
</div>
