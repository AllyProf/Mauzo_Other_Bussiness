@extends('layouts.app')

@section('title', 'Ticket Details')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-ticket"></i> Ticket #{{ $ticket->id }}</h1>
    <p>Status: 
        @if($ticket->status == 'open')
            <span class="badge badge-danger">Open</span>
        @elseif($ticket->status == 'pending')
            <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
        @elseif($ticket->status == 'resolved')
            <span class="badge badge-success">Resolved</span>
        @else
            <span class="badge badge-secondary">Closed</span>
        @endif
    </p>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Your Request</h3>
      <div class="tile-body">
        <h5>Subject: {{ $ticket->subject }}</h5>
        <hr>
        <p>{{ $ticket->message }}</p>
        <div class="mt-3 text-muted">
            Submitted: {{ $ticket->created_at->format('M d, Y H:i') }}
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Response from Platform Support</h3>
      <div class="tile-body">
        @if($ticket->admin_reply)
            <div class="p-3 bg-light border rounded" style="border-left: 4px solid #940000 !important;">
                {!! nl2br(e($ticket->admin_reply)) !!}
            </div>
            <div class="mt-3 text-muted">
                Replied on: {{ $ticket->updated_at->format('M d, Y H:i') }}
            </div>
        @else
            <div class="alert alert-info mb-0">
                <i class="fa fa-clock-o mr-2"></i> Your request is being reviewed. Check back here later — you will see the reply on this page once the platform team responds.
            </div>
        @endif
        <div class="tile-footer mt-3 px-0">
            <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Back to My Support</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
