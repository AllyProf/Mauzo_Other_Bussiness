<div class="form-group">
    <label class="control-label">Phone Number</label>
    <div class="input-group @error('phone') is-invalid @enderror">
        <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', isset($employee) && $employee->phone ? preg_replace('/^(\+255|255)/', '', $employee->phone) : '') }}"
            placeholder="712345678" maxlength="9" inputmode="numeric">
    </div>
    <small class="text-muted">Optional. 9 digits starting with 6, 7, or 8 — used for login alerts and staff SMS.</small>
    @error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>
