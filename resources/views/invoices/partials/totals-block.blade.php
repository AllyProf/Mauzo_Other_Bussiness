@php
  $vatBreakdown = $vatBreakdown ?? ($business->invoiceVatBreakdown((float) $sale->total_amount) ?? null);
  $invoiceTotal = $vatBreakdown['total'] ?? (float) $sale->total_amount;
@endphp
{{-- Standalone totals (not <tfoot>) so print shows totals only once, after all items --}}
<div class="invoice-totals-block">
  <table class="report-table invoice-totals-table mb-0">
    <tbody>
      <tr>
        <th class="text-right">{{ $vatBreakdown ? 'Subtotal (excl. VAT)' : 'Subtotal' }}</th>
        <td class="text-right">{{ money($vatBreakdown['subtotal_excl'] ?? $sale->total_amount) }}</td>
      </tr>
      @if($vatBreakdown)
      <tr>
        <th class="text-right">VAT ({{ rtrim(rtrim(number_format($vatBreakdown['rate'], 2), '0'), '.') }}%)</th>
        <td class="text-right">{{ money($vatBreakdown['vat']) }}</td>
      </tr>
      @endif
      @if($balanceDue > 0)
      <tr>
        <th class="text-right text-danger">Balance Due</th>
        <td class="text-right text-danger">{{ money($balanceDue) }}</td>
      </tr>
      @endif
      <tr class="grand-total">
        <th class="text-right">Total (TZS)</th>
        <td class="text-right amount-accent">{{ money($invoiceTotal) }}</td>
      </tr>
    </tbody>
  </table>
</div>
