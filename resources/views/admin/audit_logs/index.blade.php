@extends('layouts.app')

@section('title', 'Audit Logs - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Global Audit Logs</h1>
    <p>Track every critical action across the entire platform</p>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered table-sm" id="sampleTable">
          <thead style="background-color: #940000; color: white;">
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Business</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP Address</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->user ? $log->user->name : 'System' }}</td>
                    <td>{{ $log->business ? $log->business->name : 'Platform' }}</td>
                    <td>
                        @php
                            $badgeClass = match(true) {
                                str_contains($log->action, 'CREATE') => 'badge-success',
                                str_contains($log->action, 'SUSPEND') || str_contains($log->action, 'DELETE') => 'badge-danger',
                                str_contains($log->action, 'IMPERSONATE') => 'badge-warning',
                                str_contains($log->action, 'UPDATE') => 'badge-info',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', $log->action) }}</span>
                    </td>
                    <td>{{ $log->description }}</td>
                    <td><code>{{ $log->ip_address ?? 'N/A' }}</code></td>
                    <td>{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                </tr>
            @endforeach
            @if($logs->isEmpty())
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No audit logs found yet.</td>
                </tr>
            @endif
          </tbody>
        </table>
        <div class="mt-3">
            {{ $logs->links() }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
