@extends('layouts.app')

@section('title', 'Open Cashier Shift')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Open Cashier Shift</h1>
    <p>You collect payments on orders placed by sales officers. No stock check is needed — open your shift to start the payment queue.</p>
  </div>
  <a href="{{ route('shifts.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<div class="row">
  <div class="col-lg-6">
    <div class="tile">
      @if(!empty($assignedBranchName))
        <div class="alert alert-info py-2">
          <i class="fa fa-building"></i> You will collect payments for branch <strong>{{ $assignedBranchName }}</strong>.
        </div>
      @endif

      <form action="{{ route('shifts.store') }}" method="POST">
        @csrf
        <div class="form-group">
          <label for="opening_notes">Opening note (optional)</label>
          <textarea name="opening_notes" id="opening_notes" class="form-control" rows="3" maxlength="2000"
                    placeholder="e.g. Opening float TZS 50,000 in the drawer">{{ old('opening_notes') }}</textarea>
        </div>
        <ul class="small text-muted pl-3">
          <li>Every payment you record is counted in <strong>your</strong> handover.</li>
          <li>At the end of the shift, submit your handover — the money you collected goes to the boss.</li>
        </ul>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fa fa-play"></i> Open Shift &amp; Go to Payment Queue
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
