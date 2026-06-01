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
                    <label class="control-label">Business Email (Used as Login Username)</label>
                    <input class="form-control" type="email" name="email" placeholder="Enter Business Email" required id="email" value="{{ old('email') }}">
                </div>
                <div class="form-group">
                    <label class="control-label">Owner Password</label>
                    <div class="input-group">
                        <input class="form-control" type="text" name="password" id="password" placeholder="Generate or type password" required value="{{ old('password') }}">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" onclick="generatePassword()">Generate</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label">Phone Number</label>
                    <input class="form-control" type="text" name="phone" placeholder="e.g. 754XXXXXX" value="{{ old('phone', '+255') }}" required>
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
                    <select class="form-control" name="plan_id" id="planSelect" required>
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
                </div>
                <div class="form-group">
                    <label class="control-label">Subscription Expiry</label>
                    <input class="form-control" type="text" id="expiryPreview" readonly placeholder="Select a plan to calculate expiry">
                    <small class="text-muted">Automatically set from today based on the selected plan duration.</small>
                </div>
            </div>
          </div>

          @include('admin.businesses.partials.billing-fields', ['business' => null])

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
            districtSelect.disabled = true;
            districtSelect.appendChild(new Option('Select region first', ''));
            return;
        }

        districtSelect.disabled = false;
        districtSelect.appendChild(new Option('Select district', ''));

        (tanzaniaDistricts[region] || []).forEach(function(district) {
            const option = new Option(district, district);
            if (selectedDistrict === district) {
                option.selected = true;
            }
            districtSelect.appendChild(option);
        });
    }

    jQuery(function($) {
        $('#businessRegion').on('change', function() {
            populateDistricts(this.value, '');
        });

        $('#planSelect').on('change', updateExpiryPreview);

        populateDistricts($('#businessRegion').val(), @json(old('district', '')));
        updateExpiryPreview();
    });
</script>
@endsection
