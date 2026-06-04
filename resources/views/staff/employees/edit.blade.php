@extends('layouts.app')

@section('title', 'Edit Employee')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-user-edit"></i> Edit Employee: {{ $employee->name }}</h1>
    <p>Update staff details and role</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="tile">
        <form action="{{ route('employees.update', $employee->id) }}" method="POST" id="employeeEditForm">
            @csrf
            @method('PUT')

            @if($employee->role === 'staff')
            <div class="form-group">
                <label class="control-label">Branch <span class="text-danger">*</span></label>
                <select name="branch_id" id="branchSelect" class="form-control" required>
                    <option value="">-- Select Branch --</option>
                    @foreach($assignableBranches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}@if($branch->is_default) (Default)@endif
                        </option>
                    @endforeach
                </select>
            </div>

            @include('staff.employees.partials.branch-business-type-fields')
            @endif

            <div class="form-group">
                <label class="control-label">Full Name</label>
                <input class="form-control" type="text" name="name" value="{{ old('name', $employee->name) }}" required>
            </div>
            <div class="form-group">
                <label class="control-label">Email Address</label>
                <input class="form-control" type="email" name="email" value="{{ old('email', $employee->email) }}" required>
            </div>
            @include('staff.employees.partials.phone-field', ['employee' => $employee])
            <div class="form-group">
                <label class="control-label">Assign Role</label>
                <select name="role_id" id="roleSelect" class="form-control" required>
                    <option value="">-- Select Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ (string) old('role_id', $employee->role_id) === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <hr>
            <p class="text-muted"><i class="fa fa-info-circle"></i> Leave password fields blank if you don't want to change it.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">New Password</label>
                        <input class="form-control" type="password" name="password" placeholder="Min 6 characters">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">Confirm New Password</label>
                        <input class="form-control" type="password" name="password_confirmation" placeholder="Repeat password">
                    </div>
                </div>
            </div>

            <div class="tile-footer">
                <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Update Employee</button>
                <a class="btn btn-secondary" href="{{ route('employees.index') }}">Cancel</a>
            </div>
        </form>
    </div>
  </div>
</div>
@endsection
