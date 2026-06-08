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

          @include('admin.businesses.partials.operation-mode-fields', ['business' => $business, 'showServiceTemplates' => false])

          <div class="tile mt-3">
            <h3 class="tile-title">Owner Assignment</h3>
            <div class="tile-body">
              @if($currentOwner)
                <p class="mb-2">
                  <strong>Current owner:</strong> {{ $currentOwner->name }} ({{ $currentOwner->email }})
                </p>
              @else
                <p class="text-muted mb-2">No owner linked yet.</p>
              @endif

              @if($ownerOtherBusinesses->isNotEmpty())
                <p class="mb-2"><strong>Other businesses under this owner:</strong></p>
                <ul class="mb-3">
                  @foreach($ownerOtherBusinesses as $otherBusiness)
                    <li>{{ $otherBusiness->name }}</li>
                  @endforeach
                </ul>
              @endif

              <div class="form-group mb-0">
                <label class="control-label">Assign / Change Owner</label>
                <select class="form-control" name="owner_user_id">
                  <option value="">Keep current owner</option>
                  @foreach($existingOwners as $owner)
                    <option value="{{ $owner->id }}" {{ (string) old('owner_user_id', $business->owner_user_id) === (string) $owner->id ? 'selected' : '' }}>
                      {{ $owner->name }} — {{ $owner->email }}
                      @if($owner->owned_businesses_count > 0)
                        ({{ $owner->owned_businesses_count }} {{ Str::plural('business', $owner->owned_businesses_count) }})
                      @endif
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Use this to link a second business to an owner who already has a login.</small>
              </div>
            </div>
          </div>

          <div class="tile-footer text-right">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
            <a class="btn btn-secondary" href="{{ route('admin.businesses.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    @if($currentOwner)
    <div class="tile mt-3">
      <h3 class="tile-title"><i class="fa fa-key"></i> Reset Owner Password</h3>
      <div class="tile-body">
        <p class="text-muted mb-3">Set a new login password for <strong>{{ $currentOwner->name }}</strong> ({{ $currentOwner->email }}). Leave the field blank to auto-generate a secure password.</p>
        <form action="{{ route('admin.businesses.reset-owner-password', $business->id) }}" method="POST">
          @csrf
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Custom Password <span class="text-muted">(optional)</span></label>
                <input type="text" name="password" class="form-control" maxlength="64" autocomplete="new-password" placeholder="Leave blank to auto-generate">
                <small class="text-muted">Minimum {{ max(6, (int) platform_settings('min_password_length', 8)) }} characters if you set one manually.</small>
              </div>
            </div>
            <div class="col-md-6">
              @if($business->phone)
              <p class="text-muted mt-4 pt-2 mb-0">
                <i class="fa fa-mobile"></i> The new password will be sent by SMS to <strong>{{ $business->phone }}</strong>.
              </p>
              @else
              <p class="text-warning mt-4 pt-2 mb-0">
                <i class="fa fa-exclamation-triangle"></i> No business phone on file — SMS cannot be sent. Add a phone number above and save first.
              </p>
              @endif
            </div>
          </div>
          <button type="submit" class="btn btn-warning" onclick="confirmAction(event, 'Reset owner password?', 'The owner will need the new password to sign in. Copy it when shown — it cannot be viewed again.')">
            <i class="fa fa-refresh"></i> Reset Owner Password
          </button>
        </form>
      </div>
    </div>
    @endif

    <div class="tile mt-3 border-danger">
      <h3 class="tile-title text-danger"><i class="fa fa-trash"></i> Clear business data</h3>
      <div class="tile-body">
        <div class="alert alert-warning">
          <strong>Software owner only.</strong> Permanently deletes operational data for <strong>{{ $business->name }}</strong>.
          The business account, subscription, branches, owner login, and staff users are <strong>not</strong> removed.
          This cannot be undone.
        </div>

        <p class="mb-3">
          <span class="badge badge-dark">~{{ number_format($purgeTotalRecords ?? 0) }} records</span> across all areas (approximate).
        </p>

        <form action="{{ route('admin.businesses.purge-data', $business) }}" method="POST" id="purgeDataForm">
          @csrf
          <input type="hidden" name="purge_all" id="purgeAllFlag" value="0">

          <div class="custom-control custom-checkbox mb-3 p-2 bg-light rounded">
            <input type="checkbox" class="custom-control-input" id="purge_select_all">
            <label class="custom-control-label font-weight-bold" for="purge_select_all">Select all data areas</label>
          </div>

          <div class="row">
            @foreach($purgeScopes as $key => $scope)
            <div class="col-md-6 mb-2">
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input purge-scope-check" id="purge_{{ $key }}" name="scopes[]" value="{{ $key }}">
                <label class="custom-control-label" for="purge_{{ $key }}">
                  <strong>{{ $scope['label'] }}</strong>
                  <span class="badge badge-secondary ml-1">{{ $purgeCounts[$key] ?? 0 }}</span>
                  <br><small class="text-muted">{{ $scope['description'] }}</small>
                </label>
              </div>
            </div>
            @endforeach
          </div>

          <div class="form-group mt-3">
            <label class="font-weight-bold">Type the business name to confirm</label>
            <input type="text" name="confirm_business_name" id="confirmBusinessName" class="form-control" placeholder="{{ $business->name }}" required autocomplete="off">
            <small class="text-muted">Enter exactly: <strong>{{ $business->name }}</strong></small>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-danger mr-2 mb-2" id="purgeDataBtn">
              <i class="fa fa-trash"></i> Clear selected data
            </button>
            <button type="button" class="btn btn-outline-danger mb-2" id="purgeAllBtn">
              <i class="fa fa-exclamation-triangle"></i> Clear ALL data
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
@if(session('generated_password'))
<script>
jQuery(function($) {
  Swal.fire({
    title: 'Owner Password Reset',
    html: '<p class="mb-2">New password for <strong>{{ session('generated_password_for') }}</strong>:</p>' +
          '<div class="p-3 mb-2" style="background:#f8f9fa;border-radius:6px;font-family:monospace;font-size:1.15rem;letter-spacing:1px;">{{ session('generated_password') }}</div>' +
          '<small class="text-muted">Copy and share securely. It will not be shown again.</small>',
    icon: 'success',
    confirmButtonColor: '#940000',
    confirmButtonText: 'Done'
  });
});
</script>
@endif
@include('partials.tanzania-location-select2', ['selectedDistrict' => old('district', $business->district)])
<script type="text/javascript">
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

    jQuery(function($) {
        $('#planSelect').on('change', updateExpiryPreview);

        updateExpiryPreview();

        $('#purgeDataForm').on('submit', function (e) {
            const purgeAll = $('#purgeAllFlag').val() === '1';
            const checked = $('.purge-scope-check:checked').length;
            if (!purgeAll && checked === 0) {
                e.preventDefault();
                Swal.fire('Select data', 'Choose at least one area to clear, or use Clear ALL data.', 'warning');
                return;
            }
            e.preventDefault();
            const title = purgeAll ? 'Clear ALL business data?' : 'Clear business data?';
            const html = purgeAll
                ? 'This will permanently delete <strong>every</strong> operational data area for <strong>{{ e($business->name) }}</strong> (~{{ number_format($purgeTotalRecords ?? 0) }} records). Login, plan, branches, and users stay. <strong>Cannot be undone.</strong>'
                : 'You are about to permanently delete <strong>' + checked + '</strong> selected data area(s) for <strong>{{ e($business->name) }}</strong>. This cannot be undone.';
            Swal.fire({
                title: title,
                html: html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: purgeAll ? 'Yes, clear EVERYTHING' : 'Yes, clear data',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('purgeDataForm').submit();
                } else {
                    $('#purgeAllFlag').val('0');
                }
            });
        });

        $('#purge_select_all').on('change', function () {
            const on = $(this).is(':checked');
            $('.purge-scope-check').prop('checked', on);
        });

        $('.purge-scope-check').on('change', function () {
            const total = $('.purge-scope-check').length;
            const checked = $('.purge-scope-check:checked').length;
            $('#purge_select_all').prop('checked', total > 0 && checked === total);
        });

        $('#purgeAllBtn').on('click', function () {
            $('#purge_select_all').prop('checked', true).trigger('change');
            $('#purgeAllFlag').val('1');
            const nameOk = $('#confirmBusinessName').val().trim() === @json($business->name);
            if (!nameOk) {
                Swal.fire('Confirm name first', 'Type the exact business name in the confirmation field, then click Clear ALL data again.', 'info');
                $('#confirmBusinessName').focus();
                return;
            }
            $('#purgeDataForm').trigger('submit');
        });
    });
</script>
@endsection
