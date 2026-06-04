@php
  $plan = $plan ?? null;
  $selected = old('enabled_features', $plan ? $plan->enabledFeatures() : app(\App\Services\PlanFeatureService::class)->defaultEnabled());
  $groups = app(\App\Services\PlanFeatureService::class)->groups();
@endphp

<h5 class="mb-3">Plan Modules</h5>
<p class="text-muted small mb-3">Choose which modules businesses on this plan can access. Disabled modules are hidden from the sidebar.</p>

@foreach($groups as $groupName => $features)
  <div class="mb-3">
    <h6 class="font-weight-bold mb-2">{{ $groupName }}</h6>
    <div class="row">
      @foreach($features as $key => $label)
        <div class="col-md-6 col-lg-4">
          <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input" id="feature_{{ $key }}" name="enabled_features[]" value="{{ $key }}" {{ in_array($key, $selected, true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="feature_{{ $key }}">{{ $label }}</label>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endforeach

<div class="form-group mb-0 mt-3">
  <label class="control-label">Marketing Description</label>
  <textarea class="form-control" name="features" rows="2" placeholder="Short text for public pricing page (optional)">{{ old('features', $plan?->features) }}</textarea>
</div>
