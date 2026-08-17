@extends('layouts.app')

@section('title', __('branch_transfers.title'))

@section('content')
@php
  $user = Auth::user();
  $userBranchId = (int) ($user->branch_id ?? 0);
  $canReceivePermission = $user->can('receive_branch_supply');
  $canReceiveTransfer = function ($row) use ($user, $userBranchId, $canReceivePermission) {
      if (! $canReceivePermission || ! $row->isPending()) {
          return false;
      }
      if ($user->seesBusinessWideData() || ! $userBranchId) {
          return true;
      }
      return $userBranchId === (int) $row->to_branch_id;
  };
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-exchange"></i> {{ __('branch_transfers.title') }}</h1>
    <p>{{ __('branch_transfers.subtitle') }}</p>
  </div>
  @if($canSend && ($destinations ?? collect())->isNotEmpty())
  <a href="{{ route('branch-transfers.create') }}" class="btn btn-primary">
    <i class="fa fa-plus"></i> {{ __('branch_transfers.new') }}
  </a>
  @endif
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(($pendingIncoming ?? collect())->isNotEmpty())
<div class="tile border-warning mb-3" id="incoming">
  <div class="tile-title-w-btn">
    <h3 class="title text-warning mb-0"><i class="fa fa-download"></i> {{ __('branch_transfers.incoming') }} ({{ $pendingIncoming->count() }})</h3>
    <span class="text-muted">{{ __('branch_transfers.incoming_hint') }}</span>
  </div>
  <div class="tile-body">
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('branch_transfers.date') }}</th>
            <th>{{ __('branch_transfers.reference') }}</th>
            <th>{{ __('branch_transfers.from') }}</th>
            <th>{{ __('branch_transfers.lines') }}</th>
            <th>{{ __('branch_transfers.pieces') }}</th>
            <th>{{ __('tables.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pendingIncoming as $row)
            <tr>
              <td>{{ $row->transfer_date->format('d M Y') }}</td>
              <td><strong>{{ $row->reference_no }}</strong></td>
              <td>{{ $row->fromBranch?->name ?? '—' }}</td>
              <td>{{ $row->total_items }}</td>
              <td>{{ fmod($row->total_pieces, 1.0) === 0.0 ? (int) $row->total_pieces : number_format($row->total_pieces, 2) }}</td>
              <td>
                <a href="{{ route('branch-transfers.show', $row) }}" class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a>
                @if($canReceiveTransfer($row))
                <form action="{{ route('branch-transfers.receive', $row) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="button" class="btn btn-sm btn-success"
                          onclick='confirmAction(event, @json(__("branch_transfers.receive_confirm_title")), @json(__("branch_transfers.receive_confirm_text")))'>
                    <i class="fa fa-check"></i> {{ __('branch_transfers.receive') }}
                  </button>
                </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>{{ __('branch_transfers.stats_pending') }}</h4>
        <p><b>{{ $stats['pending'] ?? 0 }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-exchange fa-3x"></i>
      <div class="info">
        <h4>{{ __('branch_transfers.stats_supplies') }}</h4>
        <p><b>{{ $stats['total'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-cubes fa-3x"></i>
      <div class="info">
        <h4>{{ __('branch_transfers.stats_pieces') }}</h4>
        <p><b>{{ fmod($stats['pieces'], 1.0) === 0.0 ? (int) $stats['pieces'] : number_format($stats['pieces'], 2) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="tile">
  <div class="tile-title-w-btn">
    <h3 class="title">{{ __('branch_transfers.history') }}</h3>
    @if($mainBranch)
      <span class="text-muted">{{ __('branch_transfers.from') }}: <strong>{{ $mainBranch->name }}</strong></span>
    @endif
  </div>
  <div class="tile-body">
    <div class="table-responsive">
      <table class="table table-hover table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('branch_transfers.date') }}</th>
            <th>{{ __('branch_transfers.reference') }}</th>
            <th>{{ __('branch_transfers.to') }}</th>
            <th>{{ __('branch_transfers.lines') }}</th>
            <th>{{ __('branch_transfers.pieces') }}</th>
            <th>{{ __('tables.columns.status') }}</th>
            <th>{{ __('tables.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transfers as $row)
            <tr>
              <td>{{ $row->transfer_date->format('d M Y') }}</td>
              <td><strong>{{ $row->reference_no }}</strong></td>
              <td>{{ $row->toBranch?->name ?? '—' }}</td>
              <td>{{ $row->total_items }}</td>
              <td>{{ fmod($row->total_pieces, 1.0) === 0.0 ? (int) $row->total_pieces : number_format($row->total_pieces, 2) }}</td>
              <td>
                @if($row->isCancelled())
                  <span class="badge badge-secondary">{{ __('tables.status.cancelled') }}</span>
                @elseif($row->isPending())
                  <span class="badge badge-warning">{{ __('branch_transfers.pending') }}</span>
                @else
                  <span class="badge badge-success">{{ __('branch_transfers.completed') }}</span>
                @endif
              </td>
              <td>
                <a href="{{ route('branch-transfers.show', $row) }}" class="btn btn-sm btn-info" title="{{ __('tables.columns.actions') }}"><i class="fa fa-eye"></i></a>
                @if($canReceiveTransfer($row))
                <form action="{{ route('branch-transfers.receive', $row) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="button" class="btn btn-sm btn-success" title="{{ __('branch_transfers.receive') }}"
                          onclick='confirmAction(event, @json(__("branch_transfers.receive_confirm_title")), @json(__("branch_transfers.receive_confirm_text")))'>
                    <i class="fa fa-check"></i>
                  </button>
                </form>
                @endif
                @if($canUndo && ! $row->isCancelled())
                <form action="{{ route('branch-transfers.cancel', $row) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="button" class="btn btn-sm btn-outline-danger" title="{{ __('branch_transfers.cancel') }}"
                          onclick='confirmAction(event, @json(__("branch_transfers.cancel_confirm_title")), @json(__("branch_transfers.cancel_confirm_text")))'>
                    <i class="fa fa-undo"></i>
                  </button>
                </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">{{ __('branch_transfers.empty') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-3">{{ $transfers->links('pagination::bootstrap-4') }}</div>
  </div>
</div>
@endsection
