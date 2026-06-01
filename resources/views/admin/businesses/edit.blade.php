@extends('layouts.app')

@section('title', 'Update Business - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Manage Business Subscription</h1>
    <p>Update plan and contact details for {{ $business->name }}</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-10">
    <div class="tile">
      <h3 class="tile-title">Subscription & Business Info</h3>
      <div class="tile-body">
        <form action="{{ route('admin.businesses.update', $business->id) }}" method="POST" id="businessEditForm">
          @csrf
          @method('PUT')
          <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Business Name</label>
                    <input class="form-control" type="text" name="name" value="{{ old('name', $business->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="control-label">Contact Person</label>
                    <input class="form-control" type="text" name="contact_person" value="{{ old('contact_person', $business->contact_person) }}" required>
                </div>
                <div class="form-group">
                    <label class="control-label">Business Email</label>
                    <input class="form-control" type="email" name="email" value="{{ old('email', $business->email) }}" required>
                </div>
                <div class="form-group">
                    <label class="control-label">Phone Number</label>
                    <input class="form-control" type="text" name="phone" value="{{ old('phone', $business->phone) }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">TIN Number</label>
                    <input class="form-control" type="text" name="tin_number" value="{{ old('tin_number', $business->tin_number) }}" placeholder="Optional">
                </div>

                @include('partials.tanzania-location-fields', [
                    'selectedRegion' => old('region', $business->region),
                    'selectedDistrict' => old('district', $business->district),
                    'address' => old('address', $business->address),
                ])

                <hr>
                <div class="form-group">
                    <label class="control-label">Update Subscription Plan</label>
                    <select class="form-control" name="plan_id" id="planSelect" required data-current-plan="{{ $business->plan_id }}">
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}"
                                data-months="{{ $plan->duration_months }}"
                                data-billing="{{ $plan->billing_model }}"
                                data-billing-summary="{{ $plan->billingModelLabel() }} — {{ $plan->billingSummary() }}"
                                {{ (string) old('plan_id', $business->plan_id) === (string) $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} · {{ $plan->billingModelLabel() }} (TZS {{ number_format($plan->price, 0) }} / {{ $plan->duration_months }} mo)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="control-label">Subscription Expiry</label>
                    <input class="form-control" type="text" id="expiryPreview" readonly value="{{ $business->expiry_date?->format('d M, Y') }}">
                    <small class="text-muted" id="expiryHelp">Current expiry date. Changing the plan recalculates from today.</small>
                </div>
                <div class="form-group">
                    <label class="control-label">Account Status</label>
                    <select class="form-control" name="is_active" required>
                        <option value="1" {{ old('is_active', $business->is_active) ? 'selected' : '' }}>Active (Service Running)</option>
                        <option value="0" {{ ! old('is_active', $business->is_active) ? 'selected' : '' }}>Suspended (Stop Service)</option>
                    </select>
                </div>
            </div>
          </div>

          @include('admin.businesses.partials.billing-fields', ['business' => $business])

          <div class="tile-footer text-right">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
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
    const currentExpiry = @json($business->expiry_date?->format('d M, Y'));

    function formatExpiryDate(months) {
        const date = new Date();
        date.setMonth(date.getMonth() + parseInt(months || 1, 10));
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function updateExpiryPreview() {
        const select = document.getElementById('planSelect');
        const option = select.options[select.selectedIndex];
        const preview = document.getElementById('expiryPreview');
        const help = document.getElementById('expiryHelp');
        const currentPlan = select.getAttribute('data-current-plan');

        if (!option || !option.value) {
            preview.value = currentExpiry || '';
            return;
        }

        if (option.value === currentPlan) {
            preview.value = currentExpiry || '';
            help.textContent = 'Current expiry date. Change the plan to recalculate from today.';
            return;
        }

        preview.value = formatExpiryDate(option.getAttribute('data-months'));
        help.textContent = 'New expiry if you save with this plan (calculated from today).';
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

        populateDistricts($('#businessRegion').val(), @json(old('district', $business->district)));
        updateExpiryPreview();
    });
</script>
@endsection
