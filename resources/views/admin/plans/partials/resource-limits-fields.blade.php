@php $plan = $plan ?? null; @endphp

<div class="row">
  <div class="col-md-4 col-lg-2">
    <div class="form-group">
      <label class="control-label">Max Items</label>
      <input class="form-control" type="number" name="max_items" value="{{ old('max_items', $plan?->max_items ?? 100) }}" min="1" required>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="form-group">
      <label class="control-label">Max Staff</label>
      <input class="form-control" type="number" name="max_users" value="{{ old('max_users', $plan?->max_users ?? 3) }}" min="1" required>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="form-group">
      <label class="control-label">Business Types</label>
      <input class="form-control" type="number" name="max_business_types" value="{{ old('max_business_types', $plan?->max_business_types ?? 1) }}" min="0" required>
      <small class="text-muted">0 = unlimited</small>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="form-group">
      <label class="control-label">Branches</label>
      <input class="form-control" type="number" name="max_branches" value="{{ old('max_branches', $plan?->max_branches ?? 1) }}" min="0" required>
      <small class="text-muted">0 = unlimited</small>
    </div>
  </div>
  <div class="col-md-4 col-lg-3">
    @include('admin.plans.partials.storage-limit-fields', ['plan' => $plan])
  </div>
</div>
