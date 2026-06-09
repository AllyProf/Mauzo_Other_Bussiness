@forelse($tickets as $ticket)
  <div class="ticket-mobile-card">
    <div class="ticket-mobile-head">
      <div>
        <div class="ticket-mobile-id">#{{ $ticket->id }}</div>
        <div class="ticket-mobile-subject">{{ $ticket->subject }}</div>
        <div class="ticket-mobile-updated text-muted">{{ $ticket->updated_at->diffForHumans() }}</div>
      </div>
      <div class="text-right">
        @if($ticket->status == 'open')
          <span class="badge badge-danger">Open</span>
        @elseif($ticket->status == 'pending')
          <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
        @elseif($ticket->status == 'resolved')
          <span class="badge badge-success">Resolved</span>
        @else
          <span class="badge badge-secondary">Closed</span>
        @endif
        <div class="mt-1">
          @if($ticket->admin_reply)
            <span class="badge badge-success"><i class="fa fa-check"></i> Reply received</span>
          @else
            <span class="text-muted small">Waiting for reply</span>
          @endif
        </div>
      </div>
    </div>
    <div class="ticket-mobile-actions">
      <a href="{{ route('tickets.show_tenant', $ticket->id) }}" class="btn btn-sm btn-{{ $ticket->admin_reply ? 'success' : 'info' }}">
        <i class="fa fa-eye"></i> {{ $ticket->admin_reply ? 'View Reply' : 'View' }}
      </a>
    </div>
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">
    No support requests yet. Use the <strong><i class="fa fa-life-ring"></i> Support</strong> button at the bottom-right of the screen to contact us.
  </p>
@endforelse
