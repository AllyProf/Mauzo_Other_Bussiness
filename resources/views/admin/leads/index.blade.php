@extends('layouts.app')

@section('title', 'Demo Leads')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-envelope"></i> Demo Leads</h1><p>Request demo submissions from the landing page.</p></div></div>

<div class="tile mb-3">
  <div class="tile-body">
    <form method="GET" class="form-inline">
      <input type="text" name="search" class="form-control mr-2" placeholder="Search..." value="{{ request('search') }}">
      <select name="status" class="form-control mr-2" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach(['new','contacted','converted','closed'] as $s)
        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
      <button class="btn btn-primary" style="background:#940000;border-color:#940000">Filter</button>
    </form>
  </div>
</div>

<div class="tile">
  <div class="tile-body table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead><tr><th>{{ __('tables.columns.date') }}</th><th>{{ __('tables.columns.name') }}</th><th>Contact</th><th>Company</th><th>{{ __('tables.columns.message') }}</th><th>{{ __('tables.columns.status') }}</th></tr></thead>
      <tbody>
        @forelse($leads as $lead)
        <tr>
          <td>{{ $lead->created_at->format('M d, Y') }}</td>
          <td>{{ $lead->name }}</td>
          <td>{{ $lead->phone ?? '—' }}<br><small>{{ $lead->email }}</small></td>
          <td>{{ $lead->company ?? '—' }}</td>
          <td><small>{{ \Illuminate\Support\Str::limit($lead->message, 80) }}</small></td>
          <td>
            <form method="POST" action="{{ route('admin.leads.update', $lead) }}">@csrf @method('PUT')
              <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                @foreach(['new','contacted','converted','closed'] as $s)
                <option value="{{ $s }}" {{ $lead->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
              </select>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No leads yet.</td></tr>
        @endforelse
      </tbody>
    </table>
    {{ $leads->links() }}
  </div>
</div>
@endsection
