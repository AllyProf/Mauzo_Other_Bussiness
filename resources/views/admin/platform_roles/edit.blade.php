@extends('layouts.app')

@section('title', 'Edit Admin Role')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Role — {{ $role->name }}</h1>
    <p>Update name, description, and access permissions.</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="tile">
      <form method="POST" action="{{ route('admin.platform-roles.update', $role) }}">
        @csrf @method('PUT')
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Role Name</label>
              <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Description</label>
              <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label">Permissions</label>
          @include('admin.platform_roles.partials.permission-fields', [
            'currentPermissions' => old('permissions', $role->permissions ?? []),
          ])
        </div>

        <button type="submit" class="btn btn-primary" style="background:#940000;border-color:#940000"><i class="fa fa-save"></i> Update Role</button>
        <a href="{{ route('admin.platform-roles.index') }}" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
</div>
@endsection
