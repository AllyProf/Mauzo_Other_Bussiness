@extends('layouts.app')

@section('title', 'Support Tickets - Admin')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-ticket"></i> Support Tickets</h1>
    <p>Manage and respond to business owner requests</p>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Business</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($tickets as $ticket)
                <tr>
                    <td>#{{ $ticket->id }}</td>
                    <td>{{ $ticket->business->name }}</td>
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
                    <td>{{ $ticket->created_at->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="btn btn-sm btn-primary">
                            <i class="fa fa-eye"></i> View & Reply
                        </a>
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
