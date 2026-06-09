@extends('layouts.app')

@section('title', 'Edit Role')

@section('styles')
@include('staff.roles.partials.form-mobile-styles')
@endsection

@section('content')
<div class="role-form-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Role: {{ $role->name }}</h1>
    <p>Modify role name and capabilities</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-12 col-lg-10">
    <div class="tile">
      <form action="{{ route('roles.update', $role->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
          <label class="control-label">Role Name</label>
          <input class="form-control" type="text" name="name" value="{{ old('name', $role->name) }}" required>
        </div>

        <div class="form-group">
          <label class="control-label">Permissions</label>
          <p class="text-muted small mb-3">Update access for employees assigned to this role. Use presets or select permissions by module.</p>
          @include('staff.roles.partials.permission-fields', [
            'currentPermissions' => old('permissions', $role->permissions ?? []),
          ])
        </div>

        <div class="tile-footer">
          <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Update Role</button>
          <a class="btn btn-secondary" href="{{ route('roles.index') }}">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
@endsection
