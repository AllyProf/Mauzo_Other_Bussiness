@php
  $plan = $plan ?? null;
  $storedMb = (int) ($plan->max_storage_mb ?? 0);
  $defaultUnit = 'gb';
  $defaultValue = 1;

  if ($plan) {
    if ($storedMb === 0) {
      $defaultValue = 0;
    } elseif ($storedMb % 1024 === 0) {
      $defaultUnit = 'gb';
      $defaultValue = $storedMb / 1024;
    } else {
      $defaultUnit = 'mb';
      $defaultValue = $storedMb;
    }
  }

  $storageUnit = old('max_storage_unit', $defaultUnit);
  $storageValue = old('max_storage_value', $defaultValue);
@endphp

<div class="form-group mb-md-0">
  <label class="control-label">Storage Limit</label>
  <div class="input-group">
    <input class="form-control" type="number" name="max_storage_value" value="{{ $storageValue }}" min="0" step="{{ $storageUnit === 'mb' ? '1' : '0.1' }}" required>
    <select class="form-control plan-storage-unit" name="max_storage_unit" style="max-width: 80px; flex: 0 0 80px;">
      <option value="gb" {{ $storageUnit === 'gb' ? 'selected' : '' }}>GB</option>
      <option value="mb" {{ $storageUnit === 'mb' ? 'selected' : '' }}>MB</option>
    </select>
  </div>
  <small class="text-muted">0 = unlimited</small>
</div>
