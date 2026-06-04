@extends('layouts.app')

@section('title', 'Admin Roles')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-shield"></i> Admin Roles</h1>
    <p>Define what each platform admin role can access.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('admin.platform-roles.create') }}"><i class="fa fa-plus"></i> New Role</a></li>
  </ul>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="tile">
  <div class="tile-body table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead>
        <tr>
          <th>Role</th>
          <th>Access Summary</th>
          <th>Staff</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($roles as $role)
        <tr>
          <td>
            <strong>{{ $role->name }}</strong>
            @if($role->is_system)<span class="badge badge-secondary ml-1">Built-in</span>@endif
            @if($role->description)<br><small class="text-muted">{{ $role->description }}</small>@endif
          </td>
          <td><small>{{ $role->permissionSummary(5) }}</small></td>
          <td>{{ $role->users_count }}</td>
          <td class="text-nowrap text-center">
            @if($role->slug !== 'full')
            <a href="{{ route('admin.platform-roles.edit', $role) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> Edit</a>
            @endif
            @if(! $role->is_system)
            <form method="POST" action="{{ route('admin.platform-roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('Delete this role?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="text-center text-muted py-4">No roles yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('admin.staff.index') }}" class="btn btn-outline-secondary"><i class="fa fa-users"></i> Manage Staff</a>
  <a href="{{ route('admin.platform-roles.create') }}" class="btn btn-primary" style="background:#940000;border-color:#940000"><i class="fa fa-plus"></i> Create Role</a>
</div>
@endsection
