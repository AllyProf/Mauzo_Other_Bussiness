@extends('layouts.app')

@section('title', 'Subscription Plans - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-credit-card"></i> Subscription Plans</h1>
    <p>Manage the different pricing tiers for your POS software</p>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">All Plans</h3>
        <p><a class="btn btn-primary icon-btn" href="{{ route('admin.plans.create') }}"><i class="fa fa-plus"></i>Create New Plan</a></p>
      </div>
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Plan Name</th>
              <th>Billing</th>
              <th>Price / Share</th>
              <th>Duration</th>
              <th>Max Items</th>
              <th>Max Users</th>
              <th>Business Types</th>
              <th>Branches</th>
              <th>SMS / Month</th>
              <th>Email SMS / Month</th>
              <th>Features</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td><span class="badge badge-{{ $plan->usesProfitShareBilling() ? 'info' : 'secondary' }}">{{ $plan->billingModelLabel() }}</span></td>
                    <td>{{ $plan->billingSummary() }}</td>
                    <td>{{ $plan->duration_months }} Month(s)</td>
                    <td>{{ $plan->max_items ?? '-' }}</td>
                    <td>{{ $plan->max_users ?? '-' }}</td>
                    <td>{{ ($plan->max_business_types ?? 1) === 0 ? 'Unlimited' : ($plan->max_business_types ?? 1) }}</td>
                    <td>{{ ($plan->max_branches ?? 1) === 0 ? 'Unlimited' : ($plan->max_branches ?? 1) }}</td>
                    <td>{{ $plan->formatLimit($plan->max_sms ?? 0) }}</td>
                    <td>{{ $plan->formatLimit($plan->max_email_sms ?? 0) }}</td>
                    <td>{{ $plan->features ?? 'Standard Features' }}</td>
                    <td>
                        <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i> Edit</a>
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
