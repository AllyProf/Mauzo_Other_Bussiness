<div class="shortage-impact-panel">
  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <strong class="text-dark"><i class="fa fa-calculator text-primary"></i> Financial impact — {{ $check->item->name ?? 'Item' }}</strong>
    <small class="text-muted">Based on selling price &amp; cost per piece × quantity short</small>
  </div>
  <div class="row">
    <div class="col-md-2 col-6 mb-2 mb-md-0">
      <div class="impact-metric">
        <div class="label">Qty Short</div>
        <div class="value">{{ number_format($impact['shortage_qty'] ?? $check->shortageAmount(), 2) }} pcs</div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-2 mb-md-0">
      <div class="impact-metric">
        <div class="label">Unit Cost</div>
        <div class="value">{{ ($impact['unit_cost'] ?? 0) > 0 ? money($impact['unit_cost']) : '—' }}</div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-2 mb-md-0">
      <div class="impact-metric">
        <div class="label">Unit Sell</div>
        <div class="value">{{ ($impact['unit_sell'] ?? 0) > 0 ? money($impact['unit_sell']) : '—' }}</div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-2 mb-md-0">
      <div class="impact-metric highlight">
        <div class="label">Lost Revenue</div>
        <div class="value">{{ money($impact['revenue_value'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-2 mb-md-0">
      <div class="impact-metric">
        <div class="label">Lost Cost</div>
        <div class="value">{{ money($impact['cost_value'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-md-2 col-6">
      <div class="impact-metric highlight">
        <div class="label">Lost Profit</div>
        <div class="value">{{ money($impact['profit_value'] ?? 0) }}</div>
      </div>
    </div>
  </div>
  @if($check->isVerified())
    <div class="mt-3 pt-2 border-top small">
      <strong>Owner decision:</strong>
      @if($check->isWillBePaid())
        <span class="badge badge-primary">Will be paid</span>
        <span class="text-muted ml-1">Recorded for collection from staff — collect {{ money($impact['cost_value'] ?? 0) }} (cost) or {{ money($impact['revenue_value'] ?? 0) }} (sales value) outside the system or at handover.</span>
      @elseif($check->isWaived())
        <span class="badge badge-success">Waived</span>
        <span class="text-muted ml-1">No payment required from staff.</span>
      @endif
      @if($check->owner_notes)
        <div class="text-muted mt-1"><strong>Note:</strong> {{ $check->owner_notes }}</div>
      @endif
    </div>
  @endif
</div>
