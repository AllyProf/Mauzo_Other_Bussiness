@extends('layouts.app')

@section('title', 'Add Employee')

@section('styles')
<style>
  .password-toggle-wrap { position: relative; }
  .password-toggle-wrap .form-control { padding-right: 42px; }
  .password-toggle-btn {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #888;
    padding: 0;
    cursor: pointer;
    line-height: 1;
  }
  .password-toggle-btn:hover,
  .password-toggle-btn:focus {
    color: #940000;
    outline: none;
  }
  .password-strength-track {
    height: 5px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
  }
  .password-strength-bar {
    height: 100%;
    width: 0;
    border-radius: 3px;
    transition: width 0.25s ease, background-color 0.25s ease;
  }
  .password-strength-label { display: block; margin-top: 4px; font-size: 0.75rem; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-user-plus"></i> Add New Employee</h1>
    <p>Register a new staff member for your shop</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="tile">
      @if($roles->isEmpty())
        <div class="alert alert-warning">
            <i class="fa fa-warning"></i> Please <a href="{{ route('roles.create') }}">create at least one role</a> before adding employees.
        </div>
      @elseif($branches->isEmpty())
        <div class="alert alert-warning">
            <i class="fa fa-lock"></i> Please <a href="{{ route('branches.index') }}">register at least one branch</a> before adding employees.
        </div>
      @else
        <form action="{{ route('employees.store') }}" method="POST" id="employeeCreateForm">
            @csrf

            @if($errors->any())
              <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                  @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <div class="form-group">
                <label class="control-label">Full Name</label>
                <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" placeholder="Enter full name" value="{{ old('name') }}" required>
                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group">
                <label class="control-label">Email Address</label>
                <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" placeholder="Enter email address" value="{{ old('email') }}" required>
                @error('email')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group">
                <label class="control-label">Assign Role</label>
                <select name="role_id" class="form-control @error('role_id') is-invalid @enderror" required>
                    <option value="">-- Select Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role_id')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="form-group">
                <label class="control-label">Branch <span class="text-danger">*</span></label>
                <select name="branch_id" class="form-control @error('branch_id') is-invalid @enderror" required>
                    <option value="">-- Select Branch --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}@if($branch->is_default) (Default)@endif
                        </option>
                    @endforeach
                </select>
                @error('branch_id')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
            <div class="row">
                <div class="col-md-6">
                    @include('partials.password-field-tools', [
                        'inputId' => 'employeePassword',
                        'name' => 'password',
                        'label' => 'Password',
                        'required' => true,
                        'placeholder' => 'Min 6 characters',
                        'minlength' => 6,
                        'showStrength' => true,
                        'showGenerate' => true,
                    ])
                </div>
                <div class="col-md-6">
                    @include('partials.password-field-tools', [
                        'inputId' => 'employeePasswordConfirm',
                        'name' => 'password_confirmation',
                        'label' => 'Confirm Password',
                        'required' => true,
                        'placeholder' => 'Repeat password',
                        'minlength' => 6,
                    ])
                </div>
            </div>
            <div id="passwordMatchError" class="alert alert-danger d-none">Password and confirmation do not match.</div>

            <div class="tile-footer">
                <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Register Employee</button>
                <a class="btn btn-secondary" href="{{ route('employees.index') }}">Cancel</a>
            </div>
        </form>
      @endif
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  var $password = $('#employeePassword');
  var $confirm = $('#employeePasswordConfirm');
  var $bar = $('#passwordStrengthBar');
  var $label = $('#passwordStrengthLabel');

  function getPasswordStrength(password) {
    var score = 0;
    if (!password) {
      return { score: 0, label: 'Enter a password', color: '#adb5bd' };
    }
    if (password.length >= 6) score += 15;
    if (password.length >= 8) score += 10;
    if (password.length >= 12) score += 15;
    if (/[a-z]/.test(password)) score += 10;
    if (/[A-Z]/.test(password)) score += 15;
    if (/[0-9]/.test(password)) score += 15;
    if (/[^a-zA-Z0-9]/.test(password)) score += 20;

    if (score <= 25) return { score: score, label: 'Weak', color: '#dc3545' };
    if (score <= 45) return { score: score, label: 'Fair', color: '#fd7e14' };
    if (score <= 65) return { score: score, label: 'Good', color: '#ffc107' };
    if (score <= 85) return { score: score, label: 'Strong', color: '#17a2b8' };
    return { score: 100, label: 'Very Strong', color: '#28a745' };
  }

  function updateStrength() {
    var result = getPasswordStrength($password.val());
    $bar.css({ width: result.score + '%', backgroundColor: result.color });
    $label.text(result.label).css('color', result.score ? result.color : '#6c757d');
  }

  function generatePassword(length) {
    length = length || 12;
    var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    var lower = 'abcdefghjkmnpqrstuvwxyz';
    var numbers = '23456789';
    var symbols = '!@#$%&*';
    var all = upper + lower + numbers + symbols;
    var chars = [
      upper.charAt(Math.floor(Math.random() * upper.length)),
      lower.charAt(Math.floor(Math.random() * lower.length)),
      numbers.charAt(Math.floor(Math.random() * numbers.length)),
      symbols.charAt(Math.floor(Math.random() * symbols.length))
    ];

    for (var i = chars.length; i < length; i++) {
      chars.push(all.charAt(Math.floor(Math.random() * all.length)));
    }

    return chars.sort(function() { return Math.random() - 0.5; }).join('');
  }

  $('.password-toggle-btn').on('click', function() {
    var targetId = $(this).data('target');
    var $input = $('#' + targetId);
    var $icon = $(this).find('i');
    var isHidden = $input.attr('type') === 'password';

    $input.attr('type', isHidden ? 'text' : 'password');
    $icon.toggleClass('fa-eye fa-eye-slash');
    $(this).attr('aria-label', isHidden ? 'Hide password' : 'Show password');
  });

  $('#generatePasswordBtn').on('click', function() {
    var generated = generatePassword(12);
    $password.val(generated);
    $confirm.val(generated);
    $password.attr('type', 'text');
    $confirm.attr('type', 'text');
    $password.closest('.password-toggle-wrap').find('.password-toggle-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
    $confirm.closest('.password-toggle-wrap').find('.password-toggle-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
    updateStrength();
  });

  $password.on('input', updateStrength);
  updateStrength();

  $('#employeeCreateForm').on('submit', function(e) {
    if ($password.val() !== $confirm.val()) {
      e.preventDefault();
      $('#passwordMatchError').removeClass('d-none');
      $confirm.focus();
      return false;
    }
    $('#passwordMatchError').addClass('d-none');
  });

  $password.add($confirm).on('input', function() {
    if ($password.val() === $confirm.val()) {
      $('#passwordMatchError').addClass('d-none');
    }
  });
});
</script>
@endsection
