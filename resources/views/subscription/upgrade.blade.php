@extends('layouts.app')

@section('title', 'Upgrade Plan')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-level-up"></i> Upgrade Plan</h1>
    <p>Review your subscription and unlock more modules for your business.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Upgrade Plan</li>
  </ul>
</div>

@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show">
  <i class="fa fa-exclamation-triangle"></i> {{ session('warning') }}
  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="row">
  <div class="col-lg-5">
    <div class="tile">
      <h3 class="tile-title">Your Current Plan</h3>
      <div class="tile-body">
        @include('partials.subscription-billing')

        @if(($disabledFeatures ?? []) !== [])
        <hr>
        <h6 class="font-weight-bold mb-2">Not included in your plan</h6>
        <ul class="list-unstyled mb-0">
          @foreach($disabledFeatures as $featureKey)
            <li class="mb-1"><i class="fa fa-lock text-muted"></i> {{ $planFeatures->label($featureKey) }}</li>
          @endforeach
        </ul>
        @else
        <p class="text-success mb-0"><i class="fa fa-check-circle"></i> All available modules are enabled on your plan.</p>
        @endif
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tile">
      <h3 class="tile-title">Available Plans</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead style="background:#940000;color:#fff;">
              <tr>
                <th>Plan</th>
                <th>Billing</th>
                <th>Modules</th>
                <th>Limits</th>
              </tr>
            </thead>
            <tbody>
              @foreach($plans as $availablePlan)
              <tr class="{{ ($business->plan_id ?? null) === $availablePlan->id ? 'table-active' : '' }}">
                <td>
                  <strong>{{ $availablePlan->name }}</strong>
                  @if(($business->plan_id ?? null) === $availablePlan->id)
                    <span class="badge badge-primary ml-1">Current</span>
                  @endif
                </td>
                <td>{{ $availablePlan->billingSummary() }}</td>
                <td><small>{{ $planFeatures->marketingSummary($availablePlan) }}</small></td>
                <td><small>{{ $availablePlan->max_items }} items · {{ $availablePlan->max_users }} staff · {{ $availablePlan->formatStorageLimit() }}</small></td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @php $paymentInstructions = platform_settings('payment_instructions'); @endphp
        @if($paymentInstructions)
        <div class="alert alert-light border mt-3 mb-0">
          <strong><i class="fa fa-phone"></i> Request an upgrade</strong>
          <div class="mt-2" style="white-space:pre-wrap;">{{ $paymentInstructions }}</div>
          @if(platform_settings('support_email') || platform_settings('support_phone'))
          <p class="mb-0 mt-2 small text-muted">
            Contact: {{ platform_settings('support_email') }}
            @if(platform_settings('support_phone'))
              · {{ platform_settings('support_phone') }}
            @endif
          </p>
          @endif
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
