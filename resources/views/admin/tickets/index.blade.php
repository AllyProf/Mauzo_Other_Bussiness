@extends('layouts.app')

@section('title', 'Support Tickets - Admin')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-ticket"></i> Support Tickets</h1>
    <p>Manage and respond to business owner requests &mdash; SLA target: {{ App\Http\Controllers\Admin\AdminTicketController::SLA_HOURS }} hours</p>
  </div>
</div>

{{-- SLA Summary Cards --}}
<div class="row mb-3">
  <div class="col-md-4">
    <div class="tile" style="border-left: 4px solid {{ $slaBreaching->count() > 0 ? '#dc3545' : '#28a745' }};">
      <div class="tile-body d-flex align-items-center" style="gap:16px;">
        <i class="fa fa-exclamation-circle fa-2x" style="color: {{ $slaBreaching->count() > 0 ? '#dc3545' : '#28a745' }};"></i>
        <div>
          <div class="h4 mb-0 font-weight-bold">{{ $slaBreaching->count() }}</div>
          <small class="text-muted">SLA Breaching (Unanswered &gt; {{ App\Http\Controllers\Admin\AdminTicketController::SLA_HOURS }}h)</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="tile" style="border-left: 4px solid #940000;">
      <div class="tile-body d-flex align-items-center" style="gap:16px;">
        <i class="fa fa-clock-o fa-2x" style="color:#940000;"></i>
        <div>
          <div class="h4 mb-0 font-weight-bold">{{ $avgResponseTime ? number_format($avgResponseTime, 0) : '—' }} <small>min</small></div>
          <small class="text-muted">Avg. First Response Time</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="tile" style="border-left: 4px solid #6c757d;">
      <div class="tile-body d-flex align-items-center" style="gap:16px;">
        <i class="fa fa-list fa-2x text-muted"></i>
        <div>
          <div class="h4 mb-0 font-weight-bold">{{ $tickets->count() }}</div>
          <small class="text-muted">Total Tickets</small>
        </div>
      </div>
    </div>
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
              <th>{{ __('tables.columns.business') }}</th>
              <th>{{ __('tables.columns.subject') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>SLA</th>
              <th>{{ __('tables.columns.date') }}</th>
              <th>{{ __('tables.columns.action') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($tickets as $ticket)
            @php
              $ageHours = $ticket->created_at->diffInHours(now());
              $isBreaching = in_array($ticket->status, ['open','pending']) && $ageHours >= App\Http\Controllers\Admin\AdminTicketController::SLA_HOURS;
              $slaLabel = $ticket->status === 'resolved' || $ticket->status === 'closed'
                ? '<span class="badge badge-secondary">Closed</span>'
                : ($isBreaching
                    ? '<span class="badge badge-danger"><i class="fa fa-exclamation-triangle"></i> Breached</span>'
                    : '<span class="badge badge-success">Within SLA</span>');
            @endphp
            <tr class="{{ $isBreaching ? 'table-danger' : '' }}">
              <td>#{{ $ticket->id }}</td>
              <td>{{ $ticket->business->name }}</td>
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
              <td>{!! $slaLabel !!}</td>
              <td>{{ $ticket->created_at->format('M d, Y') }}</td>
              <td>
                <a href="{{ route('admin.tickets.show', $ticket->id) }}" class="btn btn-sm btn-primary">
                  <i class="fa fa-eye"></i> View &amp; Reply
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
