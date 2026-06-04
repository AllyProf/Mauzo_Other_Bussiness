@extends('layouts.app')

@section('title', 'Admin Sessions')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-desktop"></i> Admin Sessions</h1><p>View and terminate platform admin sessions.</p></div></div>

<div class="tile">
  <div class="tile-body table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead><tr><th>User</th><th>IP</th><th>Last Activity</th><th>User Agent</th><th></th></tr></thead>
      <tbody>
        @forelse($sessions as $session)
        <tr class="{{ $session->is_current ? 'table-info' : '' }}">
          <td>{{ $session->name }}<br><small>{{ $session->email }}</small>@if($session->is_current) <span class="badge badge-primary">Current</span>@endif</td>
          <td>{{ $session->ip_address }}</td>
          <td>{{ $session->last_activity_at->diffForHumans() }}</td>
          <td><small>{{ \Illuminate\Support\Str::limit($session->user_agent, 60) }}</small></td>
          <td>
            @if(!$session->is_current)
            <form method="POST" action="{{ route('admin.sessions.destroy', $session->id) }}" onsubmit="return confirm('Terminate this session?');">@csrf @method('DELETE')
              <button class="btn btn-danger btn-sm"><i class="fa fa-sign-out"></i></button>
            </form>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No admin sessions found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
