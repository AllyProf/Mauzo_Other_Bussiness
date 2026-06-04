@php
  $gross = (float) ($handoverSummary['gross_collected'] ?? 0);
  $expenses = (float) ($handoverSummary['expenses'] ?? 0);
  $final = (float) ($handoverSummary['final_handover'] ?? 0);
  $debt = (float) ($handoverSummary['debt_collected'] ?? 0);
@endphp
<div class="final-handover-banner mb-4 rounded overflow-hidden shadow-sm">
  <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #940000 0%, #6d0000 100%);">
    <div class="text-uppercase small font-weight-bold mb-1" style="letter-spacing: 0.08em; opacity: 0.9;">Final Amount to Handover</div>
    <div class="display-4 font-weight-bold mb-0">{{ money($final) }}</div>
    <div class="small mt-2" style="opacity: 0.92;">Physical cash and digital collections the staff gives to the boss</div>
  </div>
  @if($expenses > 0 || $debt > 0)
  <div class="bg-light px-4 py-3 border-top">
    <div class="row text-center small">
      <div class="col-md-4 mb-2 mb-md-0">
        <span class="text-muted d-block text-uppercase font-weight-bold">Gross Collected</span>
        <strong class="text-dark">{{ money($gross) }}</strong>
      </div>
      @if($debt > 0)
      <div class="col-md-4 mb-2 mb-md-0" style="border-left: 1px solid #dee2e6;">
        <span class="text-muted d-block text-uppercase font-weight-bold">Includes Debt Paid</span>
        <strong class="text-primary">{{ money($debt) }}</strong>
      </div>
      @endif
      @if($expenses > 0)
      <div class="col-md-4" style="{{ $debt > 0 ? 'border-left: 1px solid #dee2e6;' : '' }}">
        <span class="text-muted d-block text-uppercase font-weight-bold">Shift Expenses</span>
        <strong class="text-danger">− {{ money($expenses) }}</strong>
      </div>
      @endif
    </div>
    @if($expenses > 0)
    <div class="text-center mt-2 pt-2 border-top small text-muted">
      {{ money($gross) }} − {{ money($expenses) }} expenses = <strong class="text-dark">{{ money($final) }}</strong>
    </div>
    @endif
  </div>
  @endif
</div>
