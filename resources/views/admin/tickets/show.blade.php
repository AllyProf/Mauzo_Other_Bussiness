@extends('layouts.app')

@section('title', 'Ticket Details - Admin')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-ticket"></i> Ticket #{{ $ticket->id }}</h1>
    <p>From: {{ $ticket->business->name }} ({{ $ticket->user->name }})</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Ticket Information</h3>
      <div class="tile-body">
        <h5>Subject: {{ $ticket->subject }}</h5>
        <hr>
        <p><strong>Message:</strong></p>
        <div class="p-3 bg-light border rounded">
            {{ $ticket->message }}
        </div>
        <div class="mt-3 text-muted">
            Submitted on: {{ $ticket->created_at->format('M d, Y H:i') }}
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Reply to Business</h3>
      <div class="tile-body">
        <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="form-group">
            <label class="control-label">Status</label>
            <select name="status" class="form-control" required>
                <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                <option value="pending" {{ $ticket->status == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
          </div>
          <div class="form-group">
            <label class="control-label">Your Response</label>
            <textarea class="form-control" name="admin_reply" rows="6" required>{{ $ticket->admin_reply }}</textarea>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> Send Reply</button>
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-secondary">Back</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
