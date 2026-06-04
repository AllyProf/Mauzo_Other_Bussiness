@extends('layouts.app')

@section('title', 'Register Business - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Register New Business</h1>
    <p>Onboard a new tenant to the SP-POS platform</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-10">
    <div class="tile">
      <h3 class="tile-title">Business & Registration Details</h3>
      <div class="tile-body">
        <form action="{{ route('admin.businesses.store') }}" method="POST" id="businessCreateForm">
          @csrf

          @if ($errors->any())
          <div class="alert alert-danger">
            <strong><i class="fa fa-exclamation-circle"></i> Please fix the following:</strong>
            <ul class="mb-0 pl-3 mt-2">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
          @endif

          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Business Name</label>
                    <input class="form-control" type="text" name="name" placeholder="Enter Business Name" required value="{{ old('name') }}">
                </div>
                <div class="form-group">
                    <label class="control-label">Contact Person</label>
                    <input class="form-control" type="text" name="contact_person" placeholder="Full Name" required value="{{ old('contact_person') }}">
                </div>
                <div class="form-group">
                    <label class="control-label">Business Email</label>
                    <input class="form-control" type="email" name="email" placeholder="Enter Business Email" required id="email" value="{{ old('email') }}">
                    <small class="text-muted" id="emailHelp">Used as login username when creating a new owner account.</small>
                </div>

                <div class="form-group">
                    <label class="control-label d-block">Owner Account</label>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="ownerModeNew" name="owner_mode" value="new" class="custom-control-input" {{ old('owner_mode', 'new') === 'existing' ? '' : 'checked' }}>
                        <label class="custom-control-label" for="ownerModeNew">Create new owner login</label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="ownerModeExisting" name="owner_mode" value="existing" class="custom-control-input" {{ old('owner_mode') === 'existing' ? 'checked' : '' }}>
                        <label class="custom-control-label" for="ownerModeExisting">Link existing owner</label>
                    </div>
                </div>

                <div class="form-group" id="existingOwnerGroup" style="{{ old('owner_mode') === 'existing' ? '' : 'display:none;' }}">
                    <label class="control-label">Select Existing Owner</label>
                    <select class="form-control" name="existing_owner_id" id="existingOwnerId">
                        <option value="">Choose owner</option>
                        @foreach($existingOwners as $owner)
                            <option value="{{ $owner->id }}" {{ (string) old('existing_owner_id') === (string) $owner->id ? 'selected' : '' }}>
                                {{ $owner->name }} — {{ $owner->email }}
                                @if($owner->owned_businesses_count > 0)
                                    ({{ $owner->owned_businesses_count }} {{ Str::plural('business', $owner->owned_businesses_count) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">The owner will see this business in their business switcher after login.</small>
                </div>

                <div class="form-group" id="newOwnerPasswordGroup">
                    <label class="control-label">Owner Password</label>
                    <div class="input-group">
                        <input class="form-control @error('password') is-invalid @enderror" type="text" name="password" id="password" placeholder="Generate or type password" value="{{ old('password') }}" {{ old('owner_mode', 'new') === 'existing' ? '' : 'required' }}>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" onclick="generatePassword()">Generate</button>
                        </div>
                    </div>
                    @error('password')<small class="text-danger d-block">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label class="control-label">Phone Number</label>
                    <input class="form-control @error('phone') is-invalid @enderror" type="text" name="phone" placeholder="e.g. 754XXXXXX" value="{{ old('phone', '+255') }}" required>
                    @error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">TIN Number</label>
                    <input class="form-control" type="text" name="tin_number" placeholder="Tax Identification Number (optional)" value="{{ old('tin_number') }}">
                </div>

                @include('partials.tanzania-location-fields')

                <hr>
                <div class="form-group">
                    <label class="control-label">Select Subscription Plan</label>
                    <select class="form-control @error('plan_id') is-invalid @enderror" name="plan_id" id="planSelect" required>
                        <option value="">Choose a plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}"
                                data-months="{{ $plan->duration_months }}"
                                data-billing-summary="{{ $plan->billingModelLabel() }} — {{ $plan->billingSummary() }}"
                                {{ (string) old('plan_id') === (string) $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} · {{ $plan->billingModelLabel() }} (TZS {{ number_format($plan->price, 0) }} / {{ $plan->duration_months }} mo)
                            </option>
                        @endforeach
                    </select>
                    @error('plan_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label class="control-label">Subscription Expiry</label>
                    <input class="form-control" type="text" id="expiryPreview" readonly placeholder="Select a plan to calculate expiry">
                    <small class="text-muted">Automatically set from today based on the selected plan duration.</small>
                </div>
            </div>
          </div>

          @include('admin.businesses.partials.billing-fields', ['business' => null])

          @include('admin.businesses.partials.operation-mode-fields', ['business' => null])

          <div class="tile-footer text-right">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Register Business</button>
            <a class="btn btn-secondary" href="{{ route('admin.businesses.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script type="text/javascript">
    const tanzaniaDistricts = @json(tanzania_districts());

    function generatePassword() {
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
        let password = "";
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('password').value = password;

        Toast.fire({
            icon: 'info',
            title: 'Password generated successfully!'
        });
    }

    function formatExpiryDate(months) {
        const date = new Date();
        date.setMonth(date.getMonth() + parseInt(months || 1, 10));
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function updateExpiryPreview() {
        const select = document.getElementById('planSelect');
        const option = select.options[select.selectedIndex];
        const preview = document.getElementById('expiryPreview');

        if (!option || !option.value) {
            preview.value = '';
            return;
        }

        preview.value = formatExpiryDate(option.getAttribute('data-months'));
    }

    function populateDistricts(region, selectedDistrict) {
        const districtSelect = document.getElementById('businessDistrict');
        districtSelect.innerHTML = '';

        if (!region) {
            districtSelect.appendChild(new Option('Select region first', ''));
            return;
        }

        districtSelect.appendChild(new Option('Select district', ''));

        (tanzaniaDistricts[region] || []).forEach(function(district) {
            const option = new Option(district, district);
            if (selectedDistrict === district) {
                option.selected = true;
            }
            districtSelect.appendChild(option);
        });
    }

    function toggleOwnerMode() {
        const linkExisting = document.getElementById('ownerModeExisting').checked;
        document.getElementById('existingOwnerGroup').style.display = linkExisting ? '' : 'none';
        document.getElementById('newOwnerPasswordGroup').style.display = linkExisting ? 'none' : '';
        document.getElementById('emailHelp').textContent = linkExisting
            ? 'Business contact email. The owner keeps their existing login credentials.'
            : 'Used as login username when creating a new owner account.';
        document.getElementById('password').required = !linkExisting;
        document.getElementById('existingOwnerId').required = linkExisting;
    }

    jQuery(function($) {
        $('input[name="owner_mode"]').on('change', toggleOwnerMode);
        toggleOwnerMode();

        $('#businessRegion').on('change', function() {
            populateDistricts(this.value, '');
        });

        $('#planSelect').on('change', updateExpiryPreview);

        function toggleOperationModeFields() {
            const mode = document.querySelector('input[name="operation_mode"]:checked');
            const group = document.getElementById('serviceTemplatesGroup');
            if (!group || !mode) return;
            const show = mode.value === 'services' || mode.value === 'both';
            group.style.display = show ? '' : 'none';
        }

        document.querySelectorAll('.operation-mode-radio').forEach(function (el) {
            el.addEventListener('change', toggleOperationModeFields);
        });
        toggleOperationModeFields();

        $('#businessCreateForm').on('submit', function () {
            const region = $('#businessRegion').val();
            if (!region) {
                populateDistricts('', '');
            }
        });

        populateDistricts($('#businessRegion').val(), @json(old('district', '')));
        updateExpiryPreview();

        @if($errors->any())
        Toast.fire({
            icon: 'error',
            title: @json($errors->first())
        });
        @endif
    });
</script>
@endsection
