@extends('layouts.app')

@section('title', 'Usage Monitor')

@section('content')
<div class="app-title">
  <div><h1><i class="fa fa-heartbeat"></i> Platform Usage Monitor</h1><p>SMS, storage, and activity health per business.</p></div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link {{ $tab === 'usage' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab' => 'usage']) }}">Usage & Health</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'sms' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab' => 'sms']) }}">SMS Usage</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'storage' ? 'active' : '' }}" href="{{ route('admin.monitor.index', ['tab' => 'storage']) }}">Storage</a></li>
</ul>

<div class="tile">
  <div class="tile-body table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead>
        <tr>
          <th>{{ __('tables.columns.business') }}</th>
          <th>Plan</th>
          @if($tab === 'sms' || $tab === 'usage')
          <th>SMS (month)</th>
          @endif
          @if($tab === 'storage' || $tab === 'usage')
          <th>Storage</th>
          @endif
          @if($tab === 'usage')
          <th>{{ __('tables.columns.staff') }}</th><th>Sales 30d</th><th>Last Login</th><th>Health</th>
          @endif
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
@endsection
