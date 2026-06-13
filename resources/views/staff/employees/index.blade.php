@extends('layouts.app')

@section('title', 'Staff Members')

@section('styles')
<style>
  .employees-page .employee-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }
  .employees-page .employee-actions form {
    margin: 0;
    display: inline-flex;
  }
  .employees-page .employee-actions .btn {
    min-width: 34px;
  }
  .employees-page .emp-title-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
  .employees-page .emp-mobile-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
  }
  .employees-page .emp-mobile-card.is-inactive {
    background: #f8f9fa;
    opacity: 0.92;
  }
  .employees-page .emp-mobile-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }
  .employees-page .emp-mobile-name {
    font-weight: 700;
    color: #940000;
    font-size: 0.95rem;
    line-height: 1.35;
  }
  .employees-page .emp-mobile-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.82rem;
    color: #6c757d;
    margin-top: 4px;
    word-break: break-word;
  }
  .employees-page .emp-mobile-details {
    line-height: 1.5;
    margin-bottom: 8px;
  }
  .employees-page .emp-mobile-actions {
    padding-top: 8px;
    border-top: 1px solid #eee;
  }

  @media (max-width: 991.98px) {
    .employees-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .employees-page .app-title p { font-size: 0.88rem; }
    .employees-page .staff-limit-bar { max-width: 100% !important; }
  }

  @media (max-width: 767.98px) {
    .employees-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .employees-page .app-title h1 { font-size: 1.15rem; }
    .employees-page .emp-title-actions { width: 100%; }
    .employees-page .emp-title-actions .btn { width: 100%; text-align: center; }
  }
</style>
@endsection

@section('content')
@php
    $business = Auth::user()->business;
    $maxUsers = $business->plan->max_users ?? 0;
    $currentUsers = $staff->count();
    $percentage = $maxUsers > 0 ? min(100, ($currentUsers / $maxUsers) * 100) : 0;
    $progressColor = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
    $canAddStaff = $maxUsers == 0 || $currentUsers < $maxUsers;
    $hasBranches = \App\Models\Branch::where('business_id', $business->id)->where('is_active', true)->exists();
@endphp

<div class="employees-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> Staff Management</h1>
    <p>Manage your shop employees and their access</p>
    @can('manage_staff')
    <div class="emp-title-actions d-print-none">
      <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm {{ ($canAddStaff && $hasBranches) ? '' : 'disabled' }}" title="{{ $hasBranches ? '' : 'Register a branch first' }}"><i class="fa fa-plus"></i> Add Employee</a>
    </div>
    @endcan
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">

        @if($maxUsers > 0)
            <div class="mb-4 staff-limit-bar" style="max-width: 300px;">
                <small>Staff Limit: <strong>{{ $currentUsers }}/{{ $maxUsers }}</strong> accounts used</small>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-{{ $progressColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        @endif

        @if(! $hasBranches)
            <div class="alert alert-warning">
                <i class="fa fa-building"></i> Register at least one branch under <a href="{{ route('branches.index') }}" class="alert-link">Branches</a> before adding employees.
            </div>
        @endif

        <div class="d-lg-none mb-3">
          @include('staff.employees.partials.employee-mobile-list', ['staff' => $staff])
        </div>

        <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.name') }}</th>
              <th>{{ __('tables.columns.email') }}</th>
              <th>{{ __('tables.columns.phone') }}</th>
              <th>{{ __('tables.columns.branch') }}</th>
              <th>{{ __('tables.columns.business') }}</th>
              <th>{{ __('tables.columns.role') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th style="min-width: 220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($staff as $member)
                @php
                    $isStaffAccount = !in_array($member->role, ['owner', 'super_admin'], true);
                @endphp
                <tr class="{{ !$member->isActiveAccount() ? 'table-secondary' : '' }}">
                    <td><strong>{{ $member->name }}</strong> @if($member->id == Auth::id()) <span class="badge badge-secondary">You</span> @endif</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->phone ?? '—' }}</td>
                    <td>
                        @if($member->role === 'staff')
                            {{ $member->branch->name ?? '—' }}
                        @else
                            <span class="text-muted">All branches</span>
                        @endif
                    </td>
                    <td>
                        @if($member->role === 'staff')
                            {{ $member->displayBusinessTypeLabels() ?: '—' }}
                        @else
                            <span class="text-muted">All</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-primary">{{ $member->displayRoleName() }}</span>
                    </td>
                    <td>
                        @if($member->isActiveAccount())
                            <span class="badge badge-success">{{ __('tables.status.active') }}</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @can('manage_staff')
                        <div class="employee-actions">
                            <a href="{{ route('employees.edit', $member->id) }}" class="btn btn-sm btn-info" title="{{ __('tables.actions.edit') }}"><i class="fa fa-edit"></i></a>

                            @if($isStaffAccount)
                                @if(Auth::user()->role === 'owner' && $member->isActiveAccount() && $member->id != Auth::id())
                                <form action="{{ route('employees.impersonate', $member->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary" title="View as this staff member"
                                        onclick="confirmAction(event, 'View as {{ $member->name }}?', 'You will see the system exactly as this employee sees it. Use Switch Back to Owner when done.')">
                                        <i class="fa fa-user-secret"></i>
                                    </button>
                                </form>
                                @endif

                                <form action="{{ route('employees.reset-password', $member->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning" title="Reset / Generate Password"
                                        onclick="confirmAction(event, 'Reset Password?', 'A new random password will be generated for {{ $member->name }}. Copy it when shown — it cannot be viewed again.')">
                                        <i class="fa fa-key"></i>
                                    </button>
                                </form>

                                @if($member->id != Auth::id())
                                    <form action="{{ route('employees.toggle-status', $member->id) }}" method="POST">
                                        @csrf
                                        @if($member->isActiveAccount())
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Deactivate Account"
                                                onclick="confirmAction(event, 'Deactivate Account?', '{{ $member->name }} will not be able to log in until reactivated.')">
                                                <i class="fa fa-ban"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-success" title="Activate Account"
                                                onclick="confirmAction(event, 'Activate Account?', '{{ $member->name }} will be able to log in again.')">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        @endif
                                    </form>

                                    <form action="{{ route('employees.destroy', $member->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Remove Employee"
                                            onclick="confirmAction(event, 'Remove Employee?', 'This will permanently delete {{ $member->name }}.')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                        @endcan
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@section('scripts')
@if(session('generated_password'))
<script>
jQuery(function($) {
  Swal.fire({
    title: 'New Password Generated',
    html: '<p class="mb-2">Password for <strong>{{ session('generated_password_for') }}</strong>:</p>' +
          '<div class="p-3 mb-2" style="background:#f8f9fa;border-radius:6px;font-family:monospace;font-size:1.15rem;letter-spacing:1px;">{{ session('generated_password') }}</div>' +
          '<small class="text-muted">Copy and share securely. It will not be shown again.</small>',
    icon: 'success',
    confirmButtonColor: '#940000',
    confirmButtonText: 'Done'
  });
});
</script>
@endif
@endsection
