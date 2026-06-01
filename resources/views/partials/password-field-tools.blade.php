{{--
  Enhanced password field: toggle, optional strength meter, optional generate button.
  @param string $inputId
  @param string $name
  @param string $label
  @param bool $required
  @param string $placeholder
  @param bool $showStrength
  @param bool $showGenerate
--}}
<div class="form-group password-field-group">
  <div class="{{ !empty($showGenerate) ? 'd-flex justify-content-between align-items-center' : '' }} mb-1">
    <label class="control-label mb-0" for="{{ $inputId }}">{{ $label }}</label>
    @if(!empty($showGenerate))
      <button type="button" class="btn btn-sm btn-outline-secondary" id="generatePasswordBtn">
        <i class="fa fa-refresh"></i> Generate
      </button>
    @endif
  </div>
  <div class="password-toggle-wrap">
    <input
      class="form-control password-input"
      type="password"
      name="{{ $name }}"
      id="{{ $inputId }}"
      placeholder="{{ $placeholder ?? '' }}"
      {{ !empty($required) ? 'required' : '' }}
      @if(!empty($minlength)) minlength="{{ $minlength }}" @endif
      autocomplete="new-password"
    >
    <button type="button" class="password-toggle-btn" data-target="{{ $inputId }}" aria-label="Show password">
      <i class="fa fa-eye fa-lg"></i>
    </button>
  </div>
  @error($name)
    <small class="text-danger d-block mt-1">{{ $message }}</small>
  @enderror
  @if(!empty($showStrength))
    <div class="password-strength mt-2">
      <div class="password-strength-track">
        <div class="password-strength-bar" id="passwordStrengthBar"></div>
      </div>
      <small class="password-strength-label text-muted" id="passwordStrengthLabel">Enter a password</small>
    </div>
  @endif
</div>
