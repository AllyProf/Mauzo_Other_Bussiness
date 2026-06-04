@extends('layouts.app')

@section('title', 'Platform Staff')

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
</style>
@endsection

@section('content')
@php $minPassword = max(8, (int) platform_settings('min_password_length', 8)); @endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> Platform Staff</h1>
    <p>Create admin users and assign them a role you define.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    @if(platform_admin_can('platform_roles'))
    <li class="breadcrumb-item"><a href="{{ route('admin.platform-roles.index') }}"><i class="fa fa-shield"></i> Admin Roles</a></li>
    @endif
  </ul>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
  <div class="col-lg-5">
    <div class="tile"><h3 class="tile-title">Add Staff</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('admin.staff.store') }}" id="staffCreateForm">@csrf
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<small class="text-danger">{{ $message }}</small>@enderror
          </div>
          <div class="form-group">
            <label>Phone <span class="text-muted">(optional)</span></label>
            <div class="input-group @error('phone') is-invalid @enderror">
              <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
              <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') ? preg_replace('/^(\+255|255)/', '', old('phone')) : '' }}" placeholder="712345678" maxlength="9" inputmode="numeric">
            </div>
            <small class="text-muted">9 digits starting with 6, 7, or 8 after +255.</small>
            @error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror
          </div>
          @include('partials.password-field-tools', [
            'inputId' => 'staffPassword',
            'name' => 'password',
            'label' => 'Password',
            'required' => true,
            'placeholder' => 'Min '.$minPassword.' characters',
            'minlength' => $minPassword,
            'showGenerate' => true,
          ])
          @include('partials.password-field-tools', [
            'inputId' => 'staffPasswordConfirm',
            'name' => 'password_confirmation',
            'label' => 'Confirm Password',
            'required' => true,
            'placeholder' => 'Repeat password',
            'minlength' => $minPassword,
          ])
          <div id="passwordMatchError" class="alert alert-danger d-none py-2">Password and confirmation do not match.</div>
          <div class="form-group">
            <label>Role</label>
            <select name="platform_admin_role_id" class="form-control" required>
              @foreach($roles as $role)
              <option value="{{ $role->id }}" {{ (string) old('platform_admin_role_id') === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
              @endforeach
            </select>
            @if(platform_admin_can('platform_roles'))
            <small class="text-muted"><a href="{{ route('admin.platform-roles.index') }}">Manage roles & permissions</a></small>
            @endif
          </div>
          <button class="btn btn-primary" style="background:#940000;border-color:#940000"><i class="fa fa-save"></i> Create</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="tile"><h3 class="tile-title">Staff List</h3>
      <div class="tile-body table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Active</th><th></th></tr></thead>
          <tbody>
            @foreach($staff as $member)
            <tr>
              <td>{{ $member->name }}</td>
              <td>{{ $member->email }}</td>
              <td>{{ $member->phone ?? '—' }}</td>
              <td>{{ $member->platformAdminRole?->name ?? 'Full Access' }}</td>
              <td>{{ $member->is_active ? 'Yes' : 'No' }}</td>
              <td>
                @if($member->role === 'platform_staff')
                <form method="POST" action="{{ route('admin.staff.update', $member) }}" class="form-inline">@csrf @method('PUT')
                  <select name="platform_admin_role_id" class="form-control form-control-sm mr-1">
                    @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ (int) $member->platform_admin_role_id === (int) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                  </select>
                  <label class="mr-1"><input type="checkbox" name="is_active" value="1" {{ $member->is_active ? 'checked' : '' }}> Active</label>
                  <button class="btn btn-xs btn-primary">Save</button>
                </form>
                @else
                <span class="text-muted small">Super Admin</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  var $password = $('#staffPassword');
  var $confirm = $('#staffPasswordConfirm');

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
    var generated = generatePassword(Math.max({{ $minPassword }}, 12));
    $password.val(generated);
    $confirm.val(generated);
    $password.attr('type', 'text');
    $confirm.attr('type', 'text');
    $password.closest('.password-toggle-wrap').find('.password-toggle-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
    $confirm.closest('.password-toggle-wrap').find('.password-toggle-btn i').removeClass('fa-eye').addClass('fa-eye-slash');
  });

  $('#staffCreateForm').on('submit', function(e) {
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
