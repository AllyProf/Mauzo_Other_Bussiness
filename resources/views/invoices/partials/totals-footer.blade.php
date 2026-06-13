@php
  $vatBreakdown = $vatBreakdown ?? ($business->invoiceVatBreakdown((float) $sale->total_amount) ?? null);
  $invoiceTotal = $vatBreakdown['total'] ?? (float) $sale->total_amount;
@endphp
<tr>
  <th colspan="4" class="text-right">{{ $vatBreakdown ? 'Subtotal (excl. VAT)' : 'Subtotal' }}</th>
  <th class="text-right">{{ money($vatBreakdown['subtotal_excl'] ?? $sale->total_amount) }}</th>
</tr>
@if($vatBreakdown)
<tr>
  <th colspan="4" class="text-right">VAT ({{ rtrim(rtrim(number_format($vatBreakdown['rate'], 2), '0'), '.') }}%)</th>
  <th class="text-right">{{ money($vatBreakdown['vat']) }}</th>
</tr>
@endif
@if((float) $sale->amount_paid > 0)
<tr>
  <th colspan="4" class="text-right text-success">Amount Paid</th>
  <th class="text-right text-success">{{ money($sale->amount_paid) }}</th>
</tr>
@endif
@if($balanceDue > 0)
<tr>
  <th colspan="4" class="text-right text-danger">Balance Due</th>
  <th class="text-right text-danger">{{ money($balanceDue) }}</th>
</tr>
@endif
<tr class="grand-total">
  <th colspan="4" class="text-right">Total (TZS)</th>
  <th class="text-right">{{ money($invoiceTotal) }}</th>
</tr>
