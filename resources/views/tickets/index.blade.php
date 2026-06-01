@extends('layouts.app')

@section('title', 'Support Center')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-question-circle"></i> Support Center</h1>
    <p>Get help from the platform owner</p>
  </div>
  <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Support Ticket</a>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Last Updated</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td>#{{ $ticket->id }}</td>
                    <td>{{ $ticket->subject }}</td>
                    <td>
                        @if($ticket->status == 'open')
                            <span class="badge badge-danger">Open</span>
                        @elseif($ticket->status == 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($ticket->status == 'resolved')
                            <span class="badge badge-success">Resolved</span>
                        @else
                            <span class="badge badge-secondary">Closed</span>
                        @endif
                    </td>
                    <td>{{ $ticket->updated_at->diffForHumans() }}</td>
                    <td>
                        <a href="{{ route('tickets.show_tenant', $ticket->id) }}" class="btn btn-sm btn-info">
                            <i class="fa fa-eye"></i> View Response
                        </a>
                    </td>
                </tr>
            @endforeach
            @if($tickets->isEmpty())
                <tr>
                    <td colspan="5" class="text-center">No support tickets found.</td>
                </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
