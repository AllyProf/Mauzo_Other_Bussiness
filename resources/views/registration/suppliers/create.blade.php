@extends('layouts.app')

@section('title', 'Register Supplier')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Register Supplier</h1>
    <p>Add a new supplier to your business network</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6 offset-md-3">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('suppliers.store') }}" method="POST">
          @csrf
          <div class="form-group">
            <label class="control-label">Company Name</label>
            <input class="form-control" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Arusha Auto Parts" required>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="control-label">Type</label>
              <select class="form-control" name="supplier_type">
                <option value="">— Select —</option>
                @foreach(['Beverage', 'General', 'Spirits', 'Other'] as $type)
                  <option value="{{ $type }}" {{ old('supplier_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Contact Person</label>
              <input class="form-control" type="text" name="contact_person" value="{{ old('contact_person') }}">
            </div>
          </div>
          @if(($branches ?? collect())->count() > 1 && Auth::user()->seesBusinessWideData())
          <div class="form-group">
            <label class="control-label">Branch</label>
            <select class="form-control" name="branch_id" required>
              <option value="">-- Select Branch --</option>
              @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (int) old('branch_id', $defaultBranchId) === (int) $branch->id ? 'selected' : '' }}>
                  {{ $branch->name }}
                </option>
              @endforeach
            </select>
          </div>
          @elseif(($defaultBranchId ?? null))
            <input type="hidden" name="branch_id" value="{{ $defaultBranchId }}">
          @endif
          <div class="form-group">
            <label class="control-label">Phone Number</label>
            @include('partials.phone-country-input', [
                'id' => 'createSupplierPhone',
                'required' => true,
                'countryValue' => old('phone_country'),
                'localValue' => old('phone', ''),
            ])
            <small class="text-muted">Pick the country, then type the number without the leading 0.</small>
          </div>
          <div class="form-group">
            <label class="control-label">Email Address</label>
            @include('partials.email-suggest-input', ['id' => 'createSupplierEmail', 'value' => old('email')])
          </div>
          <div class="form-group">
            <label class="control-label">City / Region</label>
            <select class="form-control" name="region">
                <option value="">-- Select Region --</option>
                @foreach(['Arusha', 'Dar es Salaam', 'Dodoma', 'Mbeya', 'Mwanza', 'Morogoro', 'Mtwara', 'Tanga', 'Kilimanjaro', 'Zanzibar'] as $region)
                  <option value="{{ $region }}" {{ old('region') === $region ? 'selected' : '' }}>{{ $region }}</option>
                @endforeach
            </select>
          </div>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="supplierActiveCreate" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
              <label class="custom-control-label" for="supplierActiveCreate">Active supplier</label>
            </div>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Register Supplier</button>
            <a class="btn btn-secondary" href="{{ route('suppliers.index') }}"><i class="fa fa-times-circle"></i> Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
