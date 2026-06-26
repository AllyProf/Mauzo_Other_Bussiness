@extends('layouts.app')
@section('title', 'Usage Monitor')
@section('content')
<div class="app-title">
  <div><h1><i class="fa fa-heartbeat"></i> Platform Usage Monitor</h1><p>SMS, storage, server health and activity per business.</p></div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link {{ $tab === 'usage' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab'=>'usage']) }}">Usage &amp; Health</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'sms' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab'=>'sms']) }}">SMS Usage</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'storage' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab'=>'storage']) }}">Storage</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'activity_share' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab'=>'activity_share']) }}">Daily Activity Share</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'server_health' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab'=>'server_health']) }}"><i class="fa fa-server"></i> Server &amp; Queue</a></li>
</ul>

@if($tab === 'server_health')
  <div class="row">
    {{-- Cron Scheduler --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid {{ $cronStatus === 'ok' ? '#28a745' : ($cronStatus === 'stale' ? '#ffc107' : '#6c757d') }};">
        <div class="tile-body">
          <h5><i class="fa fa-clock-o"></i> Cron Scheduler</h5>
          @if($lastCronAt)
            <div class="h4 font-weight-bold">
              @if($cronStatus === 'ok')
                <span class="text-success"><i class="fa fa-check-circle"></i> Active</span>
              @else
                <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Stale</span>
              @endif
            </div>
            <p class="mb-0 text-muted small">Last ran: <strong>{{ $lastCronAt->diffForHumans() }}</strong><br>{{ $lastCronAt->format('d M Y, H:i:s') }}</p>
          @else
            <div class="h4 font-weight-bold text-secondary"><i class="fa fa-question-circle"></i> Unknown</div>
            <p class="mb-0 text-muted small">Scheduler has never recorded a heartbeat. Ensure <code>php artisan schedule:run</code> is in cron.</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Queue & Task Jobs --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid {{ $pendingJobs > 0 || $failedJobs > 0 || $scheduledSmsCount > 0 ? '#ffc107' : '#28a745' }};">
        <div class="tile-body">
          <h5><i class="fa fa-tasks"></i> Job & Task Queues</h5>
          <div class="row text-center mt-3">
            <div class="col-4">
              <div class="h3 font-weight-bold {{ $pendingJobs > 50 ? 'text-warning' : 'text-success' }}">{{ number_format($pendingJobs) }}</div>
              <small class="text-muted" style="font-size:0.72rem; display:block; white-space:nowrap;">Pending Jobs</small>
            </div>
            <div class="col-4" style="border-left: 1px solid #eee; border-right: 1px solid #eee;">
              <div class="h3 font-weight-bold {{ $failedJobs > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($failedJobs) }}</div>
              <small class="text-muted" style="font-size:0.72rem; display:block; white-space:nowrap;">Failed Jobs</small>
            </div>
            <div class="col-4">
              <div class="h3 font-weight-bold {{ $scheduledSmsCount > 0 ? 'text-warning' : 'text-success' }}">
                <a href="{{ route('admin.communication.index', ['status' => 'scheduled']) }}" style="color:inherit; text-decoration:none;">
                  {{ number_format($scheduledSmsCount) }}
                </a>
              </div>
              <small class="text-muted" style="font-size:0.72rem; display:block; white-space:nowrap;">Scheduled SMS</small>
            </div>
          </div>
          <p class="mt-3 mb-0 text-muted small">
            Queue Driver: <strong>{{ config('queue.default', 'sync') }}</strong>
            @if($scheduledSmsCount > 0)
              <br><i class="fa fa-info-circle text-warning mr-1"></i> <a href="{{ route('admin.communication.index', ['status' => 'scheduled']) }}" class="text-warning font-weight-bold">View SMS queue &rarr;</a>
            @endif
          </p>
        </div>
      </div>
    </div>

    {{-- Database --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid #940000;">
        <div class="tile-body">
          <h5><i class="fa fa-database"></i> Database</h5>
          <div class="h4 font-weight-bold">{{ number_format($dbSize, 1) }} <small>MB</small></div>
          <p class="mb-0 text-muted small">
            Driver: <strong>{{ strtoupper($driver) }}</strong><br>
            Database: <strong>{{ $dbName }}</strong>
          </p>
        </div>
      </div>
    </div>

    {{-- Cache --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid #6610f2;">
        <div class="tile-body">
          <h5><i class="fa fa-bolt"></i> Cache</h5>
          <div class="h4 font-weight-bold">{{ strtoupper($cacheDriver) }}</div>
          <p class="mb-0 text-muted small">Cache driver in use.</p>
        </div>
      </div>
    </div>

    {{-- Storage --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid #17a2b8;">
        <div class="tile-body">
          <h5><i class="fa fa-hdd-o"></i> Uploaded Files</h5>
          <div class="h4 font-weight-bold">{{ number_format($storageMb, 1) }} <small>MB</small></div>
          <p class="mb-0 text-muted small">Total size of <code>storage/app/public</code>.</p>
        </div>
      </div>
    </div>

    {{-- Runtime --}}
    <div class="col-md-4 mb-3">
      <div class="tile h-100" style="border-left: 4px solid #fd7e14;">
        <div class="tile-body">
          <h5><i class="fa fa-code"></i> Runtime</h5>
          <p class="mb-1"><strong>Laravel</strong> {{ $laravelVersion }}</p>
          <p class="mb-1"><strong>PHP</strong> {{ $phpVersion }}</p>
          <p class="mb-0 text-muted small">Server time: {{ now()->format('d M Y H:i:s') }}</p>
        </div>
      </div>
    </div>
  </div>

@elseif($tab === 'activity_share')
  <div class="tile">
    <div class="tile-body">
      <form method="GET" action="{{ route('admin.monitor.index') }}" class="form-inline mb-4 d-flex justify-content-between flex-wrap align-items-center" style="gap:15px;">
        <input type="hidden" name="tab" value="activity_share">
        <div class="d-flex align-items-center" style="gap:10px;">
          <label for="date" class="font-weight-bold mr-2 mb-0">Select Date:</label>
          <input type="date" id="date" name="date" class="form-control" value="{{ $date }}" onchange="this.form.submit()">
        </div>
        <div>
          <span class="badge badge-danger" style="font-size:1rem;padding:8px 12px;background-color:#940000;">
            Total Logged Actions: <strong>{{ number_format($totalActions) }}</strong>
          </span>
        </div>
      </form>
      <div class="table-responsive">
        <table class="table table-hover table-striped table-bordered mb-0">
          <thead>
            <tr>
              <th style="width:60px;" class="text-center">#</th>
              <th>Business / Source</th>
              <th style="width:150px;" class="text-center">Total Actions</th>
              <th>Activity Share (%)</th>
              <th style="width:150px;" class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($leaderboard as $index => $row)
            <tr>
              <td class="text-center font-weight-bold">{{ $index + 1 }}</td>
              <td>
                @if($row['business'])
                  <strong>{{ $row['business_name'] }}</strong>
                  <div class="small text-muted">Plan: {{ $row['business']->plan?->name ?? 'None' }}</div>
                @else
                  <span class="text-muted"><i class="fa fa-cogs mr-1"></i> {{ $row['business_name'] }}</span>
                @endif
              </td>
              <td class="text-center"><span class="badge badge-secondary font-weight-bold" style="font-size:0.9rem;">{{ number_format($row['count']) }}</span></td>
              <td>
                <div class="d-flex align-items-center">
                  <div class="progress flex-grow-1 mr-3" style="height:12px;border-radius:6px;background-color:#f5f5f5;">
                    <div class="progress-bar" role="progressbar" style="width:{{ $row['percent'] }}%;background-color:#940000;"></div>
                  </div>
                  <span class="font-weight-bold" style="color:#940000;min-width:50px;text-align:right;">{{ $row['percent'] }}%</span>
                </div>
              </td>
              <td class="text-center">
                <a href="{{ route('admin.audit-logs.index', ['business_id'=>$row['business_id'],'date_from'=>$date,'date_to'=>$date]) }}"
                   class="btn btn-sm btn-outline-danger" style="border-color:#940000;color:#940000;">
                  <i class="fa fa-eye mr-1"></i> View Actions
                </a>
              </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted p-4"><i class="fa fa-history fa-3x mb-2"></i><br>No user actions logged on this date.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

@else
  <div class="tile">
    <div class="tile-body table-responsive">
      <table class="table table-hover table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('tables.columns.business') }}</th>
            <th>Plan</th>
            @if($tab === 'sms' || $tab === 'usage')<th>SMS (month)</th>@endif
            @if($tab === 'storage' || $tab === 'usage')<th>Storage</th>@endif
            @if($tab === 'usage')<th>{{ __('tables.columns.staff') }}</th><th>Sales 30d</th><th>Last Login</th><th>Health</th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach(($tab === 'sms' ? $smsRows : ($tab === 'storage' ? $storageRows : $snapshots)) as $row)
          <tr>
            <td>{{ $row['business']->name }}</td>
            <td>{{ $row['business']->plan?->name ?? '—' }}</td>
            @if($tab === 'sms' || $tab === 'usage')
            <td>
              {{ $row['sms']['used'] }} / {{ $row['sms']['limit'] ?: '∞' }}
              @if($row['sms']['limit'])<span class="badge badge-{{ $row['sms']['status'] === 'critical' ? 'danger' : ($row['sms']['status'] === 'warning' ? 'warning' : 'success') }}">{{ $row['sms']['percent'] }}%</span>@endif
            </td>
            @endif
            @if($tab === 'storage' || $tab === 'usage')
            <td>
              {{ number_format($row['storage']['used_mb'], 1) }} MB / {{ $row['storage']['limit_mb'] ?: '∞' }} MB
              @if($row['storage']['limit_mb'])<span class="badge badge-{{ $row['storage']['status'] === 'critical' ? 'danger' : ($row['storage']['status'] === 'warning' ? 'warning' : 'success') }}">{{ $row['storage']['percent'] }}%</span>@endif
            </td>
            @endif
            @if($tab === 'usage')
            <td>{{ $row['staff_count'] }}</td>
            <td>{{ $row['sales_30_days'] }}</td>
            <td>{{ $row['last_login_at'] ? \Carbon\Carbon::parse($row['last_login_at'])->diffForHumans() : 'Never' }}</td>
            <td><span class="badge badge-{{ $row['health']['class'] }}">{{ $row['health']['label'] }}</span></td>
            @endif
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
