@php
  $selectedMode = old('operation_mode', $business?->operationMode() ?? \App\Models\Business::OPERATION_RETAIL);
  $serviceTemplates = config('service_templates', []);
  $selectedTemplates = old('service_template_types', []);
@endphp

<hr>
<h5 class="mb-2">Business type</h5>
<p class="text-muted small mb-3">Choose whether this tenant sells products, services, or both. This controls which menus appear after login (together with the subscription plan).</p>

<div class="form-group">
  @foreach(\App\Models\Business::operationModeOptions() as $value => $label)
  <div class="custom-control custom-radio mb-2">
    <input type="radio" class="custom-control-input operation-mode-radio" id="operation_mode_{{ $value }}" name="operation_mode" value="{{ $value }}" {{ $selectedMode === $value ? 'checked' : '' }} required>
    <label class="custom-control-label" for="operation_mode_{{ $value }}">{{ $label }}</label>
  </div>
  @endforeach
</div>

@if($showServiceTemplates ?? true)
<div id="serviceTemplatesGroup" class="border rounded p-3 bg-light mb-3" style="{{ in_array($selectedMode, [\App\Models\Business::OPERATION_SERVICES, \App\Models\Business::OPERATION_BOTH], true) ? '' : 'display:none;' }}">
  <label class="control-label font-weight-bold">Service templates <small class="text-muted font-weight-normal">(optional — import starter catalog to main branch)</small></label>
  <p class="text-muted small mb-2">Select one or more templates to pre-load categories and prices. You can add more later from <strong>Services</strong>.</p>
  <div class="row">
    @foreach($serviceTemplates as $key => $template)
    <div class="col-md-6">
      <div class="custom-control custom-checkbox mb-2">
        <input type="checkbox" class="custom-control-input" id="svc_tpl_{{ $key }}" name="service_template_types[]" value="{{ $key }}" {{ in_array($key, $selectedTemplates, true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="svc_tpl_{{ $key }}">
          <i class="fa {{ $template['icon'] ?? 'fa-briefcase' }}"></i> {{ $template['label'] ?? $key }}
        </label>
      </div>
    </div>
    @endforeach
  </div>
  <small class="text-muted">Requires the plan to include <strong>Services</strong> module for the Services menu to appear.</small>
</div>
@else
<p class="text-muted small mb-0">Changing type updates sidebar menus immediately. Import or edit service catalog from the tenant <strong>Services</strong> screen.</p>
@endif
