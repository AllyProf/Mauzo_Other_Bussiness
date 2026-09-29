@extends('layouts.app')

@section('title', 'Failed Logins')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-exclamation-triangle"></i> Failed Login Attempts</h1></div></div>

<div class="tile mb-3">
  <h3 class="tile-title mb-3">
    <i class="fa fa-lock text-danger"></i> Locked Accounts
    <span class="badge badge-{{ $lockedUsers->isEmpty() ? 'secondary' : 'danger' }} ml-1">{{ $lockedUsers->count() }}</span>
  </h3>
  <p class="text-muted small mb-3">
    Accounts are locked for {{ \App\Models\User::LOGIN_LOCK_HOURS }} hours after {{ \App\Models\User::MAX_FAILED_LOGINS }} wrong password attempts.
    Unlock one here to let the user sign in immediately.
  </p>
  <div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead><tr><th>User</th><th>Business</th><th>Role</th><th>Locked Until</th><th class="text-center" style="width:130px;">Action</th></tr></thead>
      <tbody>
        @forelse($lockedUsers as $lockedUser)
        <tr>
          <td><strong>{{ $lockedUser->name }}</strong><br><small class="text-muted">{{ $lockedUser->email }}</small></td>
          <td>{{ $lockedUser->business?->name ?? '—' }}</td>
          <td>{{ ucwords(str_replace('_', ' ', $lockedUser->role)) }}</td>
          <td>
            {{ $lockedUser->locked_until->format('d M Y, H:i') }}
            <br><small class="text-danger">{{ $lockedUser->locked_until->diffForHumans() }}</small>
          </td>
          <td class="text-center align-middle">
            <form method="POST" action="{{ route('admin.security.unlock', $lockedUser) }}" onsubmit="return confirm('Unlock {{ addslashes($lockedUser->email) }}?');">
              @csrf
              <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-unlock"></i> Unlock</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-3"><i class="fa fa-check-circle text-success"></i> No locked accounts.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="tile mb-3">
  <h3 class="tile-title mb-3">
    <i class="fa fa-ban text-danger"></i> Blocked IP Addresses
    <span class="badge badge-{{ $blockedIps->isEmpty() ? 'secondary' : 'danger' }} ml-1">{{ $blockedIps->count() }}</span>
  </h3>
  <p class="text-muted small mb-3">
    An IP address is blocked for {{ \App\Services\LoginSecurityService::IP_BLOCK_HOURS }} hours after {{ \App\Services\LoginSecurityService::IP_MAX_FAILURES }} failed sign-ins within {{ \App\Services\LoginSecurityService::IP_WINDOW_MINUTES }} minutes, for any email.
  </p>
  <div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead><tr><th>IP Address</th><th>Failed Attempts</th><th>Blocked Until</th><th class="text-center" style="width:130px;">Action</th></tr></thead>
      <tbody>
        @forelse($blockedIps as $blockedIp)
        <tr>
          <td><code>{{ $blockedIp->ip_address }}</code></td>
          <td>{{ $blockedIp->failed_attempts }}</td>
          <td>
            {{ $blockedIp->blocked_until->format('d M Y, H:i') }}
            <br><small class="text-danger">{{ $blockedIp->blocked_until->diffForHumans() }}</small>
          </td>
          <td class="text-center align-middle">
            <form method="POST" action="{{ route('admin.security.unblock-ip', $blockedIp) }}" onsubmit="return confirm('Unblock {{ $blockedIp->ip_address }}?');">
              @csrf
              <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-unlock"></i> Unblock</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="text-center text-muted py-3"><i class="fa fa-check-circle text-success"></i> No blocked IP addresses.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="tile mb-3"><div class="tile-body">
  <form method="GET" class="form-inline">
    <input type="text" name="search" class="form-control mr-2" placeholder="Email, phone, or IP" value="{{ request('search') }}">
    <input type="date" name="date_from" class="form-control mr-2" value="{{ request('date_from') }}">
    <input type="date" name="date_to" class="form-control mr-2" value="{{ request('date_to') }}">
    <button class="btn btn-primary" style="background:#940000;border-color:#940000">Filter</button>
  </form>
</div></div>

<div class="tile"><div class="tile-body table-responsive">
  <table class="table table-hover table-bordered mb-0">
    <thead><tr><th>{{ __('tables.columns.when') }}</th><th>Login</th><th>{{ __('tables.columns.ip') }}</th><th>User Agent</th></tr></thead>
    <tbody>
      @forelse($attempts as $attempt)
      <tr>
        <td>{{ $attempt->attempted_at->format('Y-m-d H:i:s') }}</td>
        <td>{{ $attempt->login_identifier }}</td>
        <td>{{ $attempt->ip_address }}</td>
        <td><small>{{ \Illuminate\Support\Str::limit($attempt->user_agent, 80) }}</small></td>
      </tr>
      @empty
      <tr><td colspan="4" class="text-center text-muted py-4">No failed attempts recorded.</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($attempts->hasPages())
  <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
    <small class="text-muted mb-2">Showing {{ $attempts->firstItem() }}–{{ $attempts->lastItem() }} of {{ $attempts->total() }}</small>
    {{ $attempts->links('pagination::bootstrap-4') }}
  </div>
  @endif
</div></div>
@endsection
