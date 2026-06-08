@extends('layouts.app')

@section('title', 'My Support')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-life-ring"></i> My Support</h1>
    <p>Your support requests and replies from the platform team</p>
  </div>
  <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Request</a>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>{{ __('tables.columns.subject') }}</th>
                <th>{{ __('tables.columns.status') }}</th>
                <th>Reply</th>
                <th>Last Updated</th>
                <th>{{ __('tables.columns.action') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tickets as $ticket)
              <tr>
                <td>#{{ $ticket->id }}</td>
                <td>{{ $ticket->subject }}</td>
                <td>
                  @if($ticket->status == 'open')
                    <span class="badge badge-danger">Open</span>
                  @elseif($ticket->status == 'pending')
                    <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
                  @elseif($ticket->status == 'resolved')
                    <span class="badge badge-success">Resolved</span>
                  @else
                    <span class="badge badge-secondary">Closed</span>
                  @endif
                </td>
                <td>
                  @if($ticket->admin_reply)
                    <span class="badge badge-success"><i class="fa fa-check"></i> Reply received</span>
                  @else
                    <span class="text-muted">Waiting for reply</span>
                  @endif
                </td>
                <td>{{ $ticket->updated_at->diffForHumans() }}</td>
                <td>
                  <a href="{{ route('tickets.show_tenant', $ticket->id) }}" class="btn btn-sm btn-{{ $ticket->admin_reply ? 'success' : 'info' }}">
                    <i class="fa fa-eye"></i> {{ $ticket->admin_reply ? 'View Reply' : 'View' }}
                  </a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  No support requests yet. Use the <strong><i class="fa fa-life-ring"></i> Support</strong> button at the bottom-right of the screen to contact us.
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
