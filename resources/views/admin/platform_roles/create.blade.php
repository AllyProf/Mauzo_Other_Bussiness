@extends('layouts.app')

@section('title', 'Create Admin Role')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Create Admin Role</h1>
    <p>Name the role and choose which platform areas it can access.</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="tile">
      <form method="POST" action="{{ route('admin.platform-roles.store') }}">
        @csrf
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Role Name</label>
              <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Billing Admin, Support Agent" required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="control-label">Description <span class="text-muted">(optional)</span></label>
              <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Short note about this role">
            </div>
          </div>
        </div>

        <div class="alert alert-light border">
          <strong><i class="fa fa-info-circle"></i> Tip</strong>
          <p class="mb-0 small">Check only the sections this admin should see in the sidebar. You can create as many custom roles as you need (Billing, Support, Read-only Reports, etc.).</p>
        </div>

        <div class="form-group">
          <label class="control-label">Permissions</label>
          @include('admin.platform_roles.partials.permission-fields', [
            'currentPermissions' => old('permissions', []),
          ])
        </div>

        <button type="submit" class="btn btn-primary" style="background:#940000;border-color:#940000"><i class="fa fa-save"></i> Save Role</button>
        <a href="{{ route('admin.platform-roles.index') }}" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
</div>
@endsection
