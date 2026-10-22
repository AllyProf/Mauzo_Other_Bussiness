@extends('layouts.app')

@section('title', 'Failed Logins')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-exclamation-triangle"></i> Failed Login Attempts</h1></div></div>

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
