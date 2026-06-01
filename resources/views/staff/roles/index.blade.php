@extends('layouts.app')

@section('title', 'Staff Roles')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-shield"></i> Staff Roles</h1>
    <p>Define roles and permissions for your employees</p>
  </div>
  <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create Role</a>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>Role Name</th>
              <th>Permissions</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($roles as $role)
                <tr>
                    <td><strong>{{ $role->name }}</strong></td>
                    <td>
                        @if($role->permissions)
                            @php
                                $permissionLabels = collect(config('permissions.groups', []))->flatMap(fn ($group) => $group);
                            @endphp
                            @foreach($role->permissions as $perm)
                                <span class="badge badge-info">{{ $permissionLabels[$perm] ?? ucwords(str_replace('_', ' ', $perm)) }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">No specific permissions</span>
                        @endif
                    </td>
                    <td>{{ $role->created_at->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i></a>
                        <form action="{{ route('roles.destroy', $role->id) }}" method="POST" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fa fa-trash"></i></button>
                        </form>
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
