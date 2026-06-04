@php
  $plan = $plan ?? null;
  $allowSms = old('allow_sms_sending', $plan ? (bool) ($plan->allow_sms_sending ?? true) : true);
  $allowEmailSms = old('allow_email_sms', $plan ? (bool) ($plan->allow_email_sms ?? true) : true);
@endphp

<div class="row mb-3">
  <div class="col-md-6">
    <div class="custom-control custom-switch">
      <input type="checkbox" class="custom-control-input" id="allow_sms_sending" name="allow_sms_sending" value="1" {{ $allowSms ? 'checked' : '' }}>
      <label class="custom-control-label" for="allow_sms_sending"><strong>Allow SMS sending</strong></label>
    </div>
    <small class="text-muted d-block mt-1">Gateway SMS to customers (new products, promotions, reminders).</small>
  </div>
  <div class="col-md-6">
    <div class="custom-control custom-switch">
      <input type="checkbox" class="custom-control-input" id="allow_email_sms" name="allow_email_sms" value="1" {{ $allowEmailSms ? 'checked' : '' }}>
      <label class="custom-control-label" for="allow_email_sms"><strong>Allow Email-to-SMS</strong></label>
    </div>
    <small class="text-muted d-block mt-1">Optional alternate channel when configured on the platform.</small>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="form-group mb-md-0" id="max_sms_group">
      <label class="control-label">Max SMS (Normal)</label>
      <input class="form-control plan-sms-limit" type="number" name="max_sms" id="max_sms" value="{{ old('max_sms', $plan?->max_sms ?? 100) }}" min="0" required>
      <small class="text-muted">Monthly limit per business. 0 = unlimited.</small>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group mb-md-0" id="max_email_sms_group">
      <label class="control-label">Max SMS via Email</label>
      <input class="form-control plan-sms-limit" type="number" name="max_email_sms" id="max_email_sms" value="{{ old('max_email_sms', $plan?->max_email_sms ?? 200) }}" min="0" required>
      <small class="text-muted">Monthly limit per business. 0 = unlimited.</small>
    </div>
  </div>
</div>
