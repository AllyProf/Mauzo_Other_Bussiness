@extends('layouts.app')

@section('title', 'Free Trial Management - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-hourglass-half"></i> Free Trial Management</h1>
    <p>Track businesses on free trials and convert them to paid subscriptions</p>
  </div>
</div>

@if($trialBusinesses->isEmpty())
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info"><i class="fa fa-info-circle mr-2"></i> No businesses are currently on a free trial or expiring within 14 days.</div>
        </div>
    </div>
@else
<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title"><i class="fa fa-list mr-2"></i> Trial / Expiring Businesses</h3>
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead style="background-color: #940000; color: white;">
            <tr>
              <th>Business</th>
              <th>Current Plan</th>
              <th>Expiry Date</th>
              <th>Days Left</th>
              <th>Extend Trial</th>
              <th>Convert to Paid</th>
            </tr>
          </thead>
          <tbody>
            @foreach($trialBusinesses as $business)
            @php
                $daysLeft = $business->expiry_date ? (int) \Carbon\Carbon::now()->diffInDays($business->expiry_date, false) : null;
                $rowClass = $daysLeft !== null && $daysLeft <= 3 ? 'table-danger' : ($daysLeft !== null && $daysLeft <= 7 ? 'table-warning' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td>
                    <strong>{{ $business->name }}</strong><br>
                    <small class="text-muted">{{ $business->email }}</small>
                </td>
                <td>
                    @if($business->plan)
                        <span class="badge badge-{{ $business->plan->price == 0 ? 'info' : 'success' }}">
                            {{ $business->plan->name }} (TZS {{ number_format($business->plan->price, 0) }})
                        </span>
                    @else
                        <span class="badge badge-secondary">No Plan</span>
                    @endif
                </td>
                <td>
                    @if($business->expiry_date)
                        {{ \Carbon\Carbon::parse($business->expiry_date)->format('M d, Y') }}
                    @else
                        <span class="text-muted">Not Set</span>
                    @endif
                </td>
                <td>
                    @if($daysLeft !== null)
                        <span class="badge badge-{{ $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'secondary') }}">
                            {{ max(0, $daysLeft) }} day(s)
                        </span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                {{-- Extend Trial --}}
                <td>
                    <form action="{{ route('admin.free-trials.extend', $business->id) }}" method="POST" class="form-inline">
                        @csrf
                        <select name="days" class="form-control form-control-sm mr-1" style="width:90px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="submit" class="btn btn-warning btn-sm" onclick="confirmAction(event, 'Extend Trial?', 'This will extend the trial period for {{ $business->name }}.')">
                            <i class="fa fa-plus"></i> Extend
                        </button>
                    </form>
                </td>
                {{-- Convert to Paid --}}
                <td>
                    <form action="{{ route('admin.free-trials.convert', $business->id) }}" method="POST" class="form-inline">
                        @csrf
                        <select name="plan_id" class="form-control form-control-sm mr-1" style="width:130px;">
                            @foreach($paidPlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="expiry_date" class="form-control form-control-sm mr-1" value="{{ \Carbon\Carbon::now()->addMonth()->toDateString() }}" style="width:135px;">
                        <button type="submit" class="btn btn-success btn-sm" onclick="confirmAction(event, 'Convert to Paid?', 'This will upgrade {{ $business->name }} to a paid subscription.')">
                            <i class="fa fa-check"></i> Convert
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif
@endsection
