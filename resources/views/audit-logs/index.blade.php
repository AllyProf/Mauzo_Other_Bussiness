@extends('layouts.app')

@section('title', 'Activity Log')

@section('styles')
<style>
  .activity-log-page .activity-mobile-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
  }
  .activity-log-page .activity-mobile-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }
  .activity-log-page .activity-mobile-time {
    font-weight: 700;
    font-size: 0.88rem;
    color: #940000;
    line-height: 1.35;
  }
  .activity-log-page .activity-mobile-user {
    font-size: 0.85rem;
    margin-top: 2px;
    line-height: 1.35;
  }
  .activity-log-page .activity-mobile-desc {
    font-size: 0.9rem;
    line-height: 1.45;
    margin-bottom: 8px;
    word-break: break-word;
  }
  .activity-log-page .activity-mobile-ip {
    font-size: 0.82rem;
    color: #6c757d;
    padding-top: 8px;
    border-top: 1px solid #eee;
  }
  .activity-log-page .activity-mobile-ip code {
    font-size: 0.82rem;
    color: #495057;
    background: #f8f9fa;
  }

  @media (max-width: 991.98px) {
    .activity-log-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .activity-log-page .app-title p { font-size: 0.88rem; }
  }

  @media (max-width: 767.98px) {
    .activity-log-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .activity-log-page .app-title h1 { font-size: 1.15rem; }
    .activity-log-page .activity-filter-actions .btn { width: 100%; margin-right: 0 !important; margin-bottom: 8px; }
    .activity-log-page .activity-filter-actions .btn:last-child { margin-bottom: 0; }
  }
</style>
@endsection

@section('content')
<div class="activity-log-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Activity Log</h1>
    <p>Track staff logins and actions in your business.</p>
  </div>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Filters</h3>
  <div class="tile-body">
    <form method="GET" action="{{ route('business.activity-log') }}" class="row">
      <div class="col-12 col-md-6 col-lg-3 form-group">
        <label class="control-label">Staff Member</label>
        <select name="user_id" class="form-control">
          <option value="">All staff</option>
          @foreach($staff as $member)
          <option value="{{ $member->id }}" {{ ($filters['user_id'] ?? '') == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-2 form-group">
        <label class="control-label">Activity Type</label>
        <select name="type" class="form-control">
          <option value="">All activity</option>
          <option value="login" {{ ($filters['type'] ?? '') === 'login' ? 'selected' : '' }}>Logins & logouts</option>
          <option value="actions" {{ ($filters['type'] ?? '') === 'actions' ? 'selected' : '' }}>Actions only</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-2 form-group">
        <label class="control-label">Action</label>
        <select name="action" class="form-control">
          <option value="">Any action</option>
          @foreach($actions as $action)
          <option value="{{ $action }}" {{ ($filters['action'] ?? '') === $action ? 'selected' : '' }}>{{ str_replace('_', ' ', $action) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-2 form-group">
        <label class="control-label">From</label>
        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-2 form-group">
        <label class="control-label">To</label>
        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
      </div>
      <div class="col-12 col-md-6 col-lg-3 form-group">
        <label class="control-label">Search</label>
        <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Search description">
      </div>
      <div class="col-12 activity-filter-actions">
        <button type="submit" class="btn btn-primary mr-2"><i class="fa fa-filter"></i> Apply</button>
        <a href="{{ route('business.activity-log') }}" class="btn btn-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="tile">
  <div class="tile-body">
    <div class="d-none d-lg-block table-responsive">
      <table class="table table-hover table-bordered table-sm mb-0">
        <thead style="background-color: #940000; color: white;">
          <tr>
            <th>{{ __('tables.columns.date_time') }}</th>
            <th>{{ __('tables.columns.user') }}</th>
            <th>{{ __('tables.columns.action') }}</th>
            <th>{{ __('tables.columns.details') }}</th>
            <th>{{ __('tables.columns.ip') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
          <tr>
            <td class="text-nowrap">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
            <td>
              {{ $log->user->name ?? 'System' }}
              @if($log->user)
              <br><small class="text-muted">{{ $log->user->displayRoleName() }}</small>
              @endif
            </td>
            <td><span class="badge {{ $log->badgeClass() }}">{{ $log->actionLabel() }}</span></td>
            <td>{{ $log->description }}</td>
            <td><code>{{ $log->ip_address ?? '—' }}</code></td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">No activity recorded yet.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-lg-none">
      @include('audit-logs.partials.activity-mobile-list', ['logs' => $logs])
    </div>

    @if($logs->hasPages())
    <div class="mt-3">{{ $logs->links() }}</div>
    @endif
  </div>
</div>
</div>
@endsection
