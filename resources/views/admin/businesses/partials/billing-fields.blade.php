@php
  $business = $business ?? null;
  $plan = $business?->plan;
  $billingSource = old('billing_model', $business?->billing_model ?? '');
  $isCustom = $billingSource !== '';
  $billingModel = $isCustom ? $billingSource : ($plan?->billing_model ?? platform_settings('default_billing_model', 'fixed_monthly'));
  $isProfitShare = $billingModel === 'profit_share';
@endphp

<hr class="my-4">
<h5 class="mb-2"><i class="fa fa-money"></i> Monthly Platform Fee</h5>
<p class="text-muted small mb-3">
  Choose how this business pays you <strong>each month</strong> — a fixed amount or a percentage of their profit.
</p>

<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <label class="billing-option-card {{ $billingSource === '' ? 'active' : '' }}" id="billingOptionPlan">
      <input type="radio" name="billing_model" value="" class="business-billing-radio" {{ $billingSource === '' ? 'checked' : '' }}>
      <strong><i class="fa fa-clone"></i> Follow selected plan</strong>
      <span class="d-block small text-muted mt-1">Use the subscription plan’s billing rules.</span>
    </label>
  </div>
  <div class="col-md-4 mb-2">
    <label class="billing-option-card {{ $billingSource === 'fixed_monthly' ? 'active' : '' }}" id="billingOptionFixed">
      <input type="radio" name="billing_model" value="fixed_monthly" class="business-billing-radio" {{ $billingSource === 'fixed_monthly' ? 'checked' : '' }}>
      <strong><i class="fa fa-calendar"></i> Fixed per month</strong>
      <span class="d-block small text-muted mt-1">Same TZS amount every billing period.</span>
    </label>
  </div>
  <div class="col-md-4 mb-2">
    <label class="billing-option-card {{ $billingSource === 'profit_share' ? 'active' : '' }}" id="billingOptionProfit">
      <input type="radio" name="billing_model" value="profit_share" class="business-billing-radio" {{ $billingSource === 'profit_share' ? 'checked' : '' }}>
      <strong><i class="fa fa-percent"></i> Percent of profit</strong>
      <span class="d-block small text-muted mt-1">Fee = % of business profit each month.</span>
    </label>
  </div>
</div>

<div class="alert alert-light border mb-3" id="planBillingPreview">
  <small class="text-muted d-block mb-1">Selected plan billing (used when “Follow selected plan” is chosen):</small>
  <strong id="planBillingPreviewText">
    @if($plan)
      {{ $plan->billingModelLabel() }} — {{ $plan->billingSummary() }}
    @else
      Select a subscription plan above to preview its monthly fee rules.
    @endif
  </strong>
</div>

<div id="businessCustomBillingFields" style="{{ $isCustom ? '' : 'display:none;' }}">
  <div class="row">
    <div class="col-md-4 billing-fixed-field" style="{{ $isCustom && ! $isProfitShare ? '' : 'display:none;' }}">
      <div class="form-group mb-md-0">
        <label class="control-label">Fixed monthly amount (TZS)</label>
        <input class="form-control" type="number" step="0.01" name="billing_price" id="business_billing_price" value="{{ old('billing_price', $business?->billing_price ?? $plan?->price ?? '') }}" min="0" placeholder="e.g. 50000">
        <small class="text-muted">Charged every billing period (plan duration).</small>
      </div>
    </div>
    <div class="col-md-4 billing-profit-field" style="{{ $isCustom && $isProfitShare ? '' : 'display:none;' }}">
      <div class="form-group mb-md-0">
        <label class="control-label">Profit share (% per month)</label>
        <input class="form-control" type="number" step="0.01" name="profit_share_percent" id="business_profit_share_percent" value="{{ old('profit_share_percent', $business?->profit_share_percent ?? $plan?->profit_share_percent ?? platform_settings('default_profit_share_percent', 5)) }}" min="0" max="100" placeholder="e.g. 5">
        <small class="text-muted">Example: 5 = you collect 5% of their profit.</small>
      </div>
    </div>
    <div class="col-md-4 billing-profit-field" style="{{ $isCustom && $isProfitShare ? '' : 'display:none;' }}">
      <div class="form-group mb-md-0">
        <label class="control-label">Profit basis</label>
        @php $basis = old('profit_share_basis', $business?->profit_share_basis ?? $plan?->profit_share_basis ?? platform_settings('default_profit_share_basis', 'net_profit')); @endphp
        <select class="form-control" name="profit_share_basis" id="business_profit_share_basis">
          <option value="net_profit" {{ $basis === 'net_profit' ? 'selected' : '' }}>Net profit (after expenses)</option>
          <option value="gross_profit" {{ $basis === 'gross_profit' ? 'selected' : '' }}>Gross profit (sales minus cost of goods)</option>
        </select>
      </div>
    </div>
  </div>
  <div class="row billing-profit-field mt-3" style="{{ $isCustom && $isProfitShare ? '' : 'display:none;' }}">
    <div class="col-md-4">
      <div class="form-group mb-0">
        <label class="control-label">Minimum monthly fee (TZS)</label>
        <input class="form-control" type="number" step="0.01" name="minimum_monthly_fee" id="business_minimum_monthly_fee" value="{{ old('minimum_monthly_fee', $business?->minimum_monthly_fee ?? $plan?->minimum_monthly_fee ?? 0) }}" min="0" placeholder="0">
        <small class="text-muted">Optional floor when profit is low. 0 = no minimum.</small>
      </div>
    </div>
  </div>
</div>

@once
  @push('styles')
  <style>
    .billing-option-card {
      display: block;
      border: 2px solid #dee2e6;
      border-radius: 6px;
      padding: 12px 14px;
      cursor: pointer;
      margin-bottom: 0;
      transition: border-color 0.2s, background 0.2s;
    }
    .billing-option-card:hover { border-color: #940000; background: #fffafa; }
    .billing-option-card.active { border-color: #940000; background: #fff5f5; }
    .billing-option-card input { margin-right: 6px; }
  </style>
  @endpush

  @push('scripts')
  <script>
    function initBusinessBillingFields() {
      var radios = document.querySelectorAll('.business-billing-radio');
      if (!radios.length) return;

      function selectedBillingModel() {
        var checked = document.querySelector('.business-billing-radio:checked');
        return checked ? checked.value : '';
      }

      function toggleBusinessBilling() {
        var model = selectedBillingModel();
        var custom = model !== '';
        var isProfit = model === 'profit_share';
        var customFields = document.getElementById('businessCustomBillingFields');
        if (customFields) customFields.style.display = custom ? '' : 'none';

        document.querySelectorAll('.billing-profit-field').forEach(function (el) {
          el.style.display = custom && isProfit ? '' : 'none';
        });
        document.querySelectorAll('.billing-fixed-field').forEach(function (el) {
          el.style.display = custom && !isProfit ? '' : 'none';
        });

        document.querySelectorAll('.billing-option-card').forEach(function (card) {
          card.classList.remove('active');
        });
        if (model === '') document.getElementById('billingOptionPlan')?.classList.add('active');
        if (model === 'fixed_monthly') document.getElementById('billingOptionFixed')?.classList.add('active');
        if (model === 'profit_share') document.getElementById('billingOptionProfit')?.classList.add('active');
      }

      function updatePlanBillingPreview() {
        var select = document.getElementById('planSelect');
        var preview = document.getElementById('planBillingPreviewText');
        if (!select || !preview) return;

        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
          preview.textContent = 'Select a subscription plan above to preview its monthly fee rules.';
          return;
        }

        var summary = option.getAttribute('data-billing-summary');
        preview.textContent = summary || 'Plan billing details unavailable.';
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', toggleBusinessBilling);
      });

      var planSelect = document.getElementById('planSelect');
      if (planSelect) {
        planSelect.addEventListener('change', updatePlanBillingPreview);
      }

      toggleBusinessBilling();
      updatePlanBillingPreview();
    }

    jQuery(function () {
      initBusinessBillingFields();
    });
  </script>
  @endpush
@endonce
