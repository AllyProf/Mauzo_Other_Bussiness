@extends('layouts.app')

@section('title', 'My Support')

@section('styles')
<style>
  .support-page .support-title-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
  .support-page .ticket-mobile-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
  }
  .support-page .ticket-mobile-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }
  .support-page .ticket-mobile-id {
    font-weight: 700;
    color: #940000;
    font-size: 0.88rem;
  }
  .support-page .ticket-mobile-subject {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.35;
    margin-top: 2px;
    word-break: break-word;
  }
  .support-page .ticket-mobile-updated {
    font-size: 0.82rem;
    margin-top: 4px;
  }
  .support-page .ticket-mobile-actions {
    padding-top: 8px;
    border-top: 1px solid #eee;
  }
  .support-page .ticket-mobile-actions .btn {
    width: 100%;
  }

  @media (max-width: 991.98px) {
    .support-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .support-page .app-title p { font-size: 0.88rem; }
  }

  @media (max-width: 767.98px) {
    .support-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .support-page .app-title h1 { font-size: 1.15rem; }
    .support-page .support-title-actions { width: 100%; }
    .support-page .support-title-actions .btn { width: 100%; text-align: center; }
  }
</style>
@endsection

@section('content')
<div class="support-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-life-ring"></i> My Support</h1>
    <p>Your support requests and replies from the platform team</p>
    <div class="support-title-actions d-print-none">
      <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New Request</a>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <div class="d-lg-none mb-3">
          @include('tickets.partials.ticket-mobile-list', ['tickets' => $tickets])
        </div>

        <div class="table-responsive d-none d-lg-block">
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
</div>
@endsection
