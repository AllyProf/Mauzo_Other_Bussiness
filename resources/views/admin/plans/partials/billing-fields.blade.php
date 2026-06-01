@php
  $plan = $plan ?? null;
  $billingModel = old('billing_model', $plan?->billing_model ?? platform_settings('default_billing_model', 'fixed_monthly'));
  $isProfitShare = $billingModel === 'profit_share';
@endphp

<hr class="my-4">
<h5 class="mb-2"><i class="fa fa-money"></i> Revenue Collection Model</h5>
<p class="text-muted small mb-3">Choose how your platform charges businesses on this plan each billing period.</p>

<div class="row">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Billing Model</label>
      <select class="form-control plan-billing-model" name="billing_model" id="billing_model">
        <option value="fixed_monthly" {{ $billingModel === 'fixed_monthly' ? 'selected' : '' }}>Fixed amount per period</option>
        <option value="profit_share" {{ $billingModel === 'profit_share' ? 'selected' : '' }}>Percentage of business profit</option>
      </select>
    </div>
  </div>
  <div class="col-md-4 billing-fixed-field">
    <div class="form-group">
      <label class="control-label">Fixed Price (TZS)</label>
      <input class="form-control" type="number" step="0.01" name="price" id="plan_price" value="{{ old('price', $plan?->price ?? '') }}" min="0">
      <small class="text-muted">Charged every billing period (see Duration).</small>
    </div>
  </div>
  <div class="col-md-4 billing-profit-field" style="{{ $isProfitShare ? '' : 'display:none;' }}">
    <div class="form-group">
      <label class="control-label">Profit Share (%)</label>
      <input class="form-control" type="number" step="0.01" name="profit_share_percent" id="profit_share_percent" value="{{ old('profit_share_percent', $plan?->profit_share_percent ?? platform_settings('default_profit_share_percent', 5)) }}" min="0" max="100">
      <small class="text-muted">Example: 5 = you collect 5% of their profit.</small>
    </div>
  </div>
</div>

<div class="row billing-profit-field" style="{{ $isProfitShare ? '' : 'display:none;' }}">
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Profit Basis</label>
      <select class="form-control" name="profit_share_basis" id="profit_share_basis">
        @php $basis = old('profit_share_basis', $plan?->profit_share_basis ?? platform_settings('default_profit_share_basis', 'net_profit')); @endphp
        <option value="net_profit" {{ $basis === 'net_profit' ? 'selected' : '' }}>Net profit (after expenses)</option>
        <option value="gross_profit" {{ $basis === 'gross_profit' ? 'selected' : '' }}>Gross profit (sales minus cost of goods)</option>
      </select>
    </div>
  </div>
  <div class="col-md-4">
    <div class="form-group">
      <label class="control-label">Minimum Monthly Fee (TZS)</label>
      <input class="form-control" type="number" step="0.01" name="minimum_monthly_fee" value="{{ old('minimum_monthly_fee', $plan?->minimum_monthly_fee ?? 0) }}" min="0">
      <small class="text-muted">Optional floor when profit is low. 0 = no minimum.</small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="alert alert-light border mb-0 h-100 d-flex align-items-center">
      <small class="text-muted mb-0">
        <strong>How it works:</strong> each month the system reads business profit from daily reports and calculates your platform fee.
      </small>
    </div>
  </div>
</div>
