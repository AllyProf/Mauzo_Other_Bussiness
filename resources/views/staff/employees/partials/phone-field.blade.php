<div class="form-group">
    <label class="control-label">Phone Number</label>
    @include('partials.phone-country-input', [
        'id' => 'employeePhone',
        'value' => isset($employee) ? $employee->phone : null,
        'countryValue' => old('phone_country'),
        'localValue' => old('phone'),
        'placeholder' => '712345678',
    ])
    <small class="text-muted">Optional. Used for login alerts and staff SMS.</small>
</div>
