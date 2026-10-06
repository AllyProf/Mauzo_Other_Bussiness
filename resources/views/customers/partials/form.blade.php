@php
  $regions = ['Arusha', 'Dar es Salaam', 'Dodoma', 'Mbeya', 'Mwanza', 'Morogoro', 'Tanga', 'Kilimanjaro', 'Zanzibar'];
@endphp

<div class="form-group">
  <label class="control-label">Customer Name</label>
  <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name', $customer->name ?? '') }}" placeholder="e.g. John Mwangi" required>
  @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
  <label class="control-label">Phone Number</label>
  @include('partials.phone-country-input', [
      'id' => isset($customer) ? 'customerPhone'.$customer->id : 'customerPhone',
      'required' => true,
      'value' => $customer->phone ?? null,
      'countryValue' => old('phone_country'),
      'localValue' => old('phone'),
      'placeholder' => '700 000 000',
  ])
  <small class="text-muted">Pick the country, then type the number without the leading 0.</small>
</div>

<div class="form-group">
  <label class="control-label">Email Address</label>
  <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" placeholder="e.g. customer@email.com">
  @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
  <label class="control-label">Address</label>
  <input class="form-control @error('address') is-invalid @enderror" type="text" name="address" value="{{ old('address', $customer->address ?? '') }}" placeholder="Street, area, or landmark">
  @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
  <label class="control-label">Region</label>
  <select class="form-control @error('region') is-invalid @enderror" name="region">
    <option value="">-- Select Region --</option>
    @foreach($regions as $region)
      <option value="{{ $region }}" {{ old('region', $customer->region ?? '') === $region ? 'selected' : '' }}>{{ $region }}</option>
    @endforeach
  </select>
  @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
  <label class="control-label">Notes</label>
  <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3" placeholder="Optional notes about this customer">{{ old('notes', $customer->notes ?? '') }}</textarea>
  @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group mb-0">
  <div class="animated-checkbox">
    <label>
      <input type="checkbox" name="is_active" value="1" {{ old('is_active', $customer->is_active ?? true) ? 'checked' : '' }}>
      <span class="label-text">Active customer (can be selected for sales)</span>
    </label>
  </div>
</div>
