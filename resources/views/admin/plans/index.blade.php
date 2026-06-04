@extends('layouts.app')

@section('title', 'Subscription Plans - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-credit-card"></i> Subscription Plans</h1>
    <p>Manage pricing tiers, resource limits, and billing models for your platform.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Subscriptions</li>
  </ul>
</div>

@if(session('success'))
<div class="row">
  <div class="col-md-12">
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fa fa-check-circle"></i> {{ session('success') }}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title"><i class="fa fa-list"></i> All Plans</h3>
        <p><a class="btn btn-primary icon-btn" href="{{ route('admin.plans.create') }}"><i class="fa fa-plus"></i>Create New Plan</a></p>
      </div>
      <div class="tile-body">
        @if($plans->isEmpty())
          <div class="text-center py-5 text-muted">
            <i class="fa fa-credit-card fa-3x mb-3 d-block"></i>
            <p class="mb-3">No subscription plans yet.</p>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create Your First Plan</a>
          </div>
        @else
        <div class="table-responsive">
          <table class="table table-hover table-bordered table-sm mb-0">
            <thead style="background-color: #940000; color: #fff;">
              <tr>
                <th>Plan Name</th>
                <th>Billing</th>
                <th>Price / Share</th>
                <th>Duration</th>
                <th class="text-center">Items</th>
                <th class="text-center">Staff</th>
                <th class="text-center">Types</th>
                <th class="text-center">Branches</th>
                <th class="text-center">Storage</th>
                <th class="text-center">SMS Channels</th>
                <th class="text-center">SMS</th>
                <th class="text-center">Email SMS</th>
                <th>Features</th>
                <th class="text-center" style="min-width: 90px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($plans as $plan)
                <tr>
                  <td><strong>{{ $plan->name }}</strong></td>
                  <td><span class="badge badge-{{ $plan->usesProfitShareBilling() ? 'info' : 'secondary' }}">{{ $plan->billingModelLabel() }}</span></td>
                  <td>{{ $plan->billingSummary() }}</td>
                  <td>{{ $plan->duration_months }} mo</td>
                  <td class="text-center">{{ $plan->max_items ?? '—' }}</td>
                  <td class="text-center">{{ $plan->max_users ?? '—' }}</td>
                  <td class="text-center">{{ ($plan->max_business_types ?? 1) === 0 ? '∞' : ($plan->max_business_types ?? 1) }}</td>
                  <td class="text-center">{{ ($plan->max_branches ?? 1) === 0 ? '∞' : ($plan->max_branches ?? 1) }}</td>
                  <td class="text-center">{{ $plan->formatStorageLimit() }}</td>
                  <td class="text-center"><small>{{ $plan->smsChannelLabel() }}</small></td>
                  <td class="text-center">{{ $plan->allowsSmsSending() ? $plan->formatLimit($plan->max_sms ?? 0) : '—' }}</td>
                  <td class="text-center">{{ $plan->formatLimit($plan->max_email_sms ?? 0) }}</td>
                  <td><small>{{ Str::limit($plan->features ?? 'Standard features', 40) }}</small></td>
                  <td class="text-center">
                    <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn btn-sm btn-info" title="Edit plan"><i class="fa fa-edit"></i></a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
