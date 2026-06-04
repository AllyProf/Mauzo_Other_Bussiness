@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-user-circle"></i> My Profile</h1>
    <p>Manage your account information</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Profile</li>
  </ul>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="tile text-center">
            <h3 class="tile-title">Profile Image</h3>
            <div class="tile-body">
                <div class="profile-img-preview mb-3">
                    @if($user->profile_image)
                        <img src="{{ asset('storage/' . $user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" alt="Profile" class="rounded-circle shadow" style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #fff;">
                    @else
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto shadow" style="width: 150px; height: 150px; border: 4px solid #fff;">
                            <i class="fa fa-user fa-5x text-muted"></i>
                        </div>
                    @endif
                </div>
                <h4 class="font-weight-bold">{{ $user->name }}</h4>
                <p class="text-muted">
                    @if($isStaff)
                        <span class="badge badge-info">{{ $user->displayRoleName() }}</span>
                    @elseif($user->role === 'super_admin')
                        <span class="badge badge-primary">Software Owner</span>
                    @elseif($user->role === 'platform_staff')
                        <span class="badge badge-secondary">Platform Staff</span>
                    @else
                        <span class="badge badge-primary">Business Owner</span>
                    @endif
                </p>
                <hr>
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group text-left">
                        <label class="font-weight-bold">Update Phone Number</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light font-weight-bold">255</span>
                            </div>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone', $phoneLocal) }}"
                                   placeholder="e.g. 712345678">
                            @error('phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted">Enter number without the 255 prefix</small>
                    </div>

                    <div class="form-group text-left">
                        <label class="font-weight-bold">Change Profile Image</label>
                        <input type="file" name="profile_image" class="form-control-file @error('profile_image') is-invalid @enderror" accept="image/jpeg,image/png,image/jpg">
                        <small class="text-muted">Max size 2MB (JPG, PNG)</small>
                        @error('profile_image')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="tile">
            <h3 class="tile-title"><i class="fa fa-lock"></i> Security & Password</h3>
            <div class="tile-body">
                <form action="{{ route('profile.update-password') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           id="password"
                                           name="password"
                                           required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="mt-2" id="password-strength-container">
                                    <div class="progress" style="height: 5px;">
                                        <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small id="strength-text" class="text-muted"></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password_confirmation">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password"
                                           class="form-control"
                                           id="password_confirmation"
                                           name="password_confirmation"
                                           required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirmation">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-danger">
                            <i class="fa fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tile">
            <h3 class="tile-title"><i class="fa fa-info-circle"></i> Account Information</h3>
            <div class="tile-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="30%">Full Name:</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th>Email Address:</th>
                        <td>{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <th>Account ID / Role:</th>
                        <td>
                            @if($isStaff)
                                <code class="bg-light p-1">#{{ $user->id }}</code> ({{ $user->displayRoleName() }})
                            @elseif($user->role === 'super_admin')
                                <span class="badge badge-dark">Software Owner</span>
                            @elseif($user->role === 'platform_staff')
                                <span class="badge badge-secondary">{{ $user->platformAdminRole?->name ?? 'Platform Staff' }}</span>
                            @else
                                <span class="badge badge-dark">Business Owner</span>
                            @endif
                        </td>
                    </tr>
                    @if($user->business && !$user->isPlatformAdmin())
                    <tr>
                        <th>Business:</th>
                        <td>{{ $user->business->name }}</td>
                    </tr>
                    @endif
                    @if($user->branch)
                    <tr>
                        <th>Branch:</th>
                        <td>{{ $user->branch->name }}</td>
                    </tr>
                    @endif
                    <tr>
                        <th>Member Since:</th>
                        <td>{{ $user->created_at->format('d M, Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    passwordInput?.addEventListener('input', function () {
        const val = this.value;
        let strength = 0;
        let text = '';
        let color = '';

        if (val.length >= 8) strength += 25;
        if (/[A-Z]/.test(val)) strength += 25;
        if (/[a-z]/.test(val)) strength += 10;
        if (/[0-9]/.test(val)) strength += 20;
        if (/[^A-Za-z0-9]/.test(val)) strength += 20;

        if (strength <= 25) { text = 'Weak'; color = 'bg-danger'; }
        else if (strength <= 50) { text = 'Moderate'; color = 'bg-warning'; }
        else if (strength <= 75) { text = 'Strong'; color = 'bg-info'; }
        else { text = 'Very Strong'; color = 'bg-success'; }

        strengthBar.style.width = strength + '%';
        strengthBar.className = 'progress-bar ' + color;
        strengthText.innerText = text;
    });
</script>
@if($errors->any())
<script>
    Swal.fire({
        icon: 'error',
        title: 'Could not save',
        html: @json(implode('<br>', $errors->all())),
        confirmButtonColor: '#940000'
    });
</script>
@endif
@endsection
