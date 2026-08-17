@extends('layouts.app')

@section('title', __('branch_transfers.title').' #'.$transfer->reference_no)

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-exchange"></i> {{ $transfer->reference_no }}</h1>
    <p>{{ $transfer->fromBranch?->name }} → {{ $transfer->toBranch?->name }}</p>
  </div>
  <div>
    <a href="{{ route('branch-transfers.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i> {{ __('branch_transfers.back') }}</a>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fa fa-print"></i> {{ __('branch_transfers.print') }}</button>
    @if($canReceive)
    <form action="{{ route('branch-transfers.receive', $transfer) }}" method="POST" class="d-inline">
      @csrf
      <button type="button" class="btn btn-success btn-sm" onclick='confirmAction(event, @json(__("branch_transfers.receive_confirm_title")), @json(__("branch_transfers.receive_confirm_text")))'>
        <i class="fa fa-check"></i> {{ __('branch_transfers.receive') }}
      </button>
    </form>
    @endif
    @if($canUndo)
    <form action="{{ route('branch-transfers.cancel', $transfer) }}" method="POST" class="d-inline">
      @csrf
      <button type="button" class="btn btn-outline-danger btn-sm" onclick='confirmAction(event, @json(__("branch_transfers.cancel_confirm_title")), @json(__("branch_transfers.cancel_confirm_text")))'>
        <i class="fa fa-undo"></i> {{ __('branch_transfers.cancel') }}
      </button>
    </form>
    @endif
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($transfer->isPending() && $canReceive)
  <div class="alert alert-warning">
    <i class="fa fa-download"></i> {{ __('branch_transfers.receive_hint') }}
  </div>
@elseif($transfer->isPending())
  <div class="alert alert-warning">
    <i class="fa fa-clock-o"></i> {{ __('branch_transfers.awaiting_hint', ['branch' => $transfer->toBranch?->name]) }}
  </div>
@elseif($transfer->isCompleted() && $canUndo)
  <div class="alert alert-light border">
    <i class="fa fa-undo text-danger"></i> {{ __('branch_transfers.undo_hint') }}
  </div>
@endif

<div class="tile">
  <div class="row mb-3">
    <div class="col-md-6">
      <p class="mb-1"><strong>{{ __('branch_transfers.reference') }}:</strong> {{ $transfer->reference_no }}</p>
      <p class="mb-1"><strong>{{ __('branch_transfers.date') }}:</strong> {{ $transfer->transfer_date->format('d M Y') }}</p>
      <p class="mb-1"><strong>{{ __('branch_transfers.from') }}:</strong> {{ $transfer->fromBranch?->name }}</p>
      <p class="mb-1"><strong>{{ __('branch_transfers.to') }}:</strong> {{ $transfer->toBranch?->name }}</p>
    </div>
    <div class="col-md-6">
      <p class="mb-1"><strong>{{ __('branch_transfers.supplied_by') }}:</strong> {{ $transfer->user?->name }}</p>
      <p class="mb-1"><strong>{{ __('tables.columns.status') }}:</strong>
        @if($transfer->isCancelled())
          <span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
        @elseif($transfer->isPending())
          <span class="badge badge-warning">{{ __('branch_transfers.pending') }}</span>
        @else
          <span class="badge badge-success">{{ __('branch_transfers.completed') }}</span>
        @endif
      </p>
      @if($transfer->receivedBy)
        <p class="mb-1"><strong>{{ __('branch_transfers.received_by') }}:</strong> {{ $transfer->receivedBy->name }}</p>
      @endif
      @if($transfer->received_at)
        <p class="mb-1"><strong>{{ __('branch_transfers.received_at') }}:</strong> {{ $transfer->received_at->format('d M Y h:i A') }}</p>
      @endif
      @if($transfer->notes)
        <p class="mb-1"><strong>{{ __('branch_transfers.notes') }}:</strong> {{ $transfer->notes }}</p>
      @endif
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>{{ __('branch_transfers.item') }}</th>
          <th>{{ __('branch_transfers.incoming_qty') }}</th>
          <th>{{ __('branch_transfers.available_now') }} ({{ $stockBranchName }})</th>
          @if($showAfterReceive)
            <th>{{ __('branch_transfers.available_after') }}</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($lines as $row)
          <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['qty_label'] }}</td>
            <td>{{ $row['available_now'] }}</td>
            @if($showAfterReceive)
              <td><strong>{{ $row['available_after'] }}</strong></td>
            @endif
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <th>{{ __('branch_transfers.pieces') }}</th>
          <th>{{ fmod($transfer->total_pieces, 1.0) === 0.0 ? (int) $transfer->total_pieces : number_format($transfer->total_pieces, 2) }} pcs</th>
          <th colspan="{{ !empty($showAfterReceive) ? '2' : '1' }}"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endsection
