@extends('layouts.app')

@section('title', 'Edit Plan - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Plan</h1>
    <p>Update subscription tier details</p>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Plan: {{ $plan->name }}</h3>
      <div class="tile-body">
        <form action="{{ route('admin.plans.update', $plan->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Plan Name</label>
                <input class="form-control" type="text" name="name" value="{{ old('name', $plan->name) }}" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Duration (Months)</label>
                <input class="form-control" type="number" name="duration_months" value="{{ old('duration_months', $plan->duration_months) }}" min="1" required>
              </div>
            </div>
          </div>

          @include('admin.plans.partials.billing-fields', ['plan' => $plan])

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Max Items</label>
                <input class="form-control" type="number" name="max_items" value="{{ old('max_items', $plan->max_items) }}" min="1" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Max Staff Users</label>
                <input class="form-control" type="number" name="max_users" value="{{ old('max_users', $plan->max_users) }}" min="1" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Max Business Types</label>
                <input class="form-control" type="number" name="max_business_types" value="{{ old('max_business_types', $plan->max_business_types ?? 1) }}" min="0" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="control-label">Max Branches</label>
                <input class="form-control" type="number" name="max_branches" value="{{ old('max_branches', $plan->max_branches ?? 1) }}" min="0" required>
              </div>
            </div>
          </div>

          <hr class="my-4">
          <h5 class="mb-2"><i class="fa fa-commenting"></i> Messaging Limits</h5>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Max SMS (Normal)</label>
                <input class="form-control" type="number" name="max_sms" value="{{ old('max_sms', $plan->max_sms ?? 100) }}" min="0" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="control-label">Max SMS via Email</label>
                <input class="form-control" type="number" name="max_email_sms" value="{{ old('max_email_sms', $plan->max_email_sms ?? 200) }}" min="0" required>
              </div>
            </div>
          </div>

          <div class="form-group mb-0">
            <label class="control-label">Features</label>
            <textarea class="form-control" name="features" rows="4">{{ old('features', $plan->features) }}</textarea>
          </div>

          <div class="tile-footer px-0 pb-0">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Update Plan</button>
            <a class="btn btn-secondary" href="{{ route('admin.plans.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  function toggleBillingFields() {
    var model = document.getElementById('billing_model').value;
    var isProfit = model === 'profit_share';
    document.querySelectorAll('.billing-profit-field').forEach(function (el) {
      el.style.display = isProfit ? '' : 'none';
    });
    document.querySelectorAll('.billing-fixed-field').forEach(function (el) {
      el.style.display = isProfit ? 'none' : '';
    });
  }

  var select = document.getElementById('billing_model');
  if (select) {
    select.addEventListener('change', toggleBillingFields);
    toggleBillingFields();
  }
})();
</script>
@endsection
