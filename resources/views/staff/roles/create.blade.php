@extends('layouts.app')

@section('title', 'Create Role')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> Create New Role</h1>
    <p>Define what staff can access across POS, invoices, inventory, reconciliation, and business setup</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-10">
    <div class="tile">
      <form action="{{ route('roles.store') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="control-label">Role Name</label>
          <input class="form-control" type="text" name="name" placeholder="e.g. Cashier, Store Manager, Supervisor" value="{{ old('name') }}" required>
        </div>

        <div class="alert alert-light border mb-4">
          <strong><i class="fa fa-info-circle"></i> How permissions work</strong>
          <ul class="mb-0 mt-2 small pl-3">
            <li><strong>Cashier</strong> — POS, invoices, payments, and daily reconciliation for their own shift.</li>
            <li><strong>Store Manager</strong> — Full sales floor plus inventory receiving and debt management.</li>
            <li><strong>Supervisor</strong> — Oversight, verification, and reports without day-to-day POS.</li>
            <li>Use a preset below as a starting point, then fine-tune individual checkboxes.</li>
          </ul>
        </div>

        <div class="form-group">
          <label class="control-label">Permissions</label>
          <p class="text-muted small mb-3">Select what this role can access. Permissions are grouped by module to match the sidebar menu.</p>
          @include('staff.roles.partials.permission-fields', [
            'currentPermissions' => old('permissions', []),
          ])
        </div>

        <div class="tile-footer">
          <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Create Role</button>
          <a class="btn btn-secondary" href="{{ route('roles.index') }}">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
