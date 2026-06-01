@extends('layouts.app')

@section('title', 'Stock Shortages')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-warning"></i> Stock Shortages</h1>
    <p>Items recorded short during shift opening or closing stock checks — review and verify each entry.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('shifts.index') }}">Shifts</a></li>
    <li class="breadcrumb-item active">Stock Shortages</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
  <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
  <div class="col-md-3">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Opening Shortages</h4>
        <p><b>{{ number_format($stats['opening_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-arrow-down fa-3x"></i>
      <div class="info">
        <h4>Closing Shortages</h4>
        <p><b>{{ number_format($stats['closing_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>On Open Shifts</h4>
        <p><b>{{ number_format($stats['open_shift_shortages']) }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-hourglass-half fa-3x"></i>
      <div class="info">
        <h4>Awaiting Review</h4>
        <p><b>{{ number_format($stats['pending_verification']) }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Shortage Log</h3>
  <div class="tile-body">
    <form method="GET" action="{{ route('stock-shortages.index') }}" class="row mb-3">
      <div class="col-md-4 mb-2 mb-md-0">
        <label class="small font-weight-bold">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Item, staff, or notes...">
      </div>
      <div class="col-md-3 mb-2 mb-md-0">
        <label class="small font-weight-bold">Review</label>
        <select name="status" class="form-control form-control-sm">
          <option value="">All</option>
          <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Awaiting review</option>
          <option value="verified" {{ request('status') === 'verified' ? 'selected' : '' }}>Verified</option>
        </select>
      </div>
      <div class="col-md-3 mb-2 mb-md-0">
        <label class="small font-weight-bold">Staff</label>
        <select name="staff_id" class="form-control form-control-sm">
          <option value="">All staff</option>
          @foreach($staffMembers as $member)
            <option value="{{ $member->id }}" {{ (string) request('staff_id') === (string) $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> Search</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover table-bordered table-sm">
        <thead class="thead-dark">
          <tr>
            <th>Date / Time</th>
            <th>Shift</th>
            <th>Officer</th>
            <th>Check</th>
            <th>Item</th>
            <th class="text-right">System</th>
            <th class="text-right">Counted</th>
            <th class="text-right">Short By</th>
            <th>Reason / Notes</th>
            <th>Status</th>
            <th class="text-center" style="min-width:120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($shortages as $check)
            <tr class="{{ $check->isVerified() ? 'table-light' : 'table-danger' }}">
              <td nowrap>{{ $check->recorded_at->format('d M, Y h:i A') }}</td>
              <td>
                <a href="{{ route('shifts.show', $check->shift) }}">#{{ $check->shift_id }}</a>
                @if($check->shift->status === 'open')
                  <span class="badge badge-success">Open</span>
                @endif
              </td>
              <td>{{ $check->shift->user->name ?? '—' }}</td>
              <td><span class="badge badge-{{ $check->check_type === 'opening' ? 'primary' : 'secondary' }}">{{ ucfirst($check->check_type) }}</span></td>
              <td>
                <strong>{{ $check->item->name ?? 'Item' }}</strong>
                @if($check->item?->category)
                  <br><small class="text-muted">{{ $check->item->category->name }}</small>
                @endif
              </td>
              <td class="text-right">{{ number_format($check->system_stock, 2) }}</td>
              <td class="text-right">{{ number_format($check->counted_stock, 2) }}</td>
              <td class="text-right font-weight-bold text-danger">{{ number_format($check->shortageAmount(), 2) }}</td>
              <td style="max-width:200px;">
                {{ $check->notes ?: '—' }}
                @if($check->owner_notes)
                  <br><small class="text-success"><strong>Owner:</strong> {{ $check->owner_notes }}</small>
                @endif
              </td>
              <td>
                @if($check->isVerified())
                  <span class="badge badge-success"><i class="fa fa-check"></i> Verified</span>
                  <br><small class="text-muted">{{ $check->verified_at->format('d M, h:i A') }}</small>
                @else
                  <span class="badge badge-warning">Pending</span>
                @endif
              </td>
              <td class="text-center text-nowrap">
                @if(! $check->isVerified())
                  <form action="{{ route('stock-shortages.verify', $check) }}" method="POST" class="verify-shortage-form d-inline">
                    @csrf
                    <input type="hidden" name="owner_notes" value="">
                    <button type="button" class="btn btn-xs btn-success verify-shortage-btn" title="Mark as reviewed">
                      <i class="fa fa-check"></i> Verify
                    </button>
                  </form>
                @else
                  <span class="text-muted small" title="Verified by {{ $check->verifier->name ?? 'Owner' }}"><i class="fa fa-lock"></i></span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="11" class="text-center py-4 text-muted">No stock shortages recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
      {{ $shortages->links() }}
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  $('.verify-shortage-btn').on('click', function() {
    const form = $(this).closest('form');
    const btn = $(this);

    Swal.fire({
      title: 'Verify this stock shortage?',
      text: 'Confirm you have reviewed the staff reason and accept the recorded shortage.',
      input: 'textarea',
      inputLabel: 'Owner note (optional)',
      inputPlaceholder: 'e.g. Accepted — damaged stock removed',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      confirmButtonText: '<i class="fa fa-check"></i> Verify',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        form.find('input[name="owner_notes"]').val(result.value || '');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        form.submit();
      }
    });
  });
});
</script>
@endsection
