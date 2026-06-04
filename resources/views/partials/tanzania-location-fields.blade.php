@php
  $selectedRegion = old('region', $selectedRegion ?? '');
  $selectedDistrict = old('district', $selectedDistrict ?? '');
  $districtOptions = $selectedRegion ? tanzania_districts($selectedRegion) : [];
@endphp

<div class="form-group">
  <label class="control-label">Region <span class="text-danger">*</span></label>
  <select class="form-control @error('region') is-invalid @enderror" name="region" id="businessRegion" required>
    <option value="">Select region</option>
    @foreach(tanzania_regions() as $region)
      <option value="{{ $region }}" {{ $selectedRegion === $region ? 'selected' : '' }}>{{ $region }}</option>
    @endforeach
  </select>
  @error('region')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>

<div class="form-group">
  <label class="control-label">District <span class="text-danger">*</span></label>
  <select class="form-control @error('district') is-invalid @enderror" name="district" id="businessDistrict" required>
    <option value="">{{ $selectedRegion ? 'Select district' : 'Select region first' }}</option>
    @foreach($districtOptions as $district)
      <option value="{{ $district }}" {{ $selectedDistrict === $district ? 'selected' : '' }}>{{ $district }}</option>
    @endforeach
  </select>
  @error('district')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>

<div class="form-group">
  <label class="control-label">Physical Address <span class="text-danger">*</span></label>
  <textarea class="form-control @error('address') is-invalid @enderror" name="address" rows="2" placeholder="Street, building, plot number, landmark" required>{{ old('address', $address ?? '') }}</textarea>
  <small class="text-muted">Detailed location within the selected district.</small>
  @error('address')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>
