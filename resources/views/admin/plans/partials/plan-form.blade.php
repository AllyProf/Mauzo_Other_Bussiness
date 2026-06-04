@php $plan = $plan ?? null; @endphp

<form action="{{ $formAction }}" method="POST" id="planForm">
  @csrf
  @if(($formMethod ?? 'POST') === 'PUT')
    @method('PUT')
  @endif

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0 pl-3">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Plan Name</label>
        <input class="form-control" type="text" name="name" placeholder="e.g. Basic, Professional" required value="{{ old('name', $plan?->name) }}">
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label class="control-label">Duration (Months)</label>
        <input class="form-control" type="number" name="duration_months" value="{{ old('duration_months', $plan?->duration_months ?? 1) }}" min="1" required>
        <small class="text-muted">Billing cycle length for fixed-fee plans.</small>
      </div>
    </div>
  </div>

  <hr>

  @include('admin.plans.partials.billing-fields', ['plan' => $plan])

  <hr>

  <h5 class="mb-3">Resource Limits</h5>
  @include('admin.plans.partials.resource-limits-fields', ['plan' => $plan])

  <hr>

  <h5 class="mb-3">Messaging</h5>
  <p class="text-muted small mb-3">Enable SMS channels and set monthly limits. Turn off channels for plans that should not send messages.</p>
  @include('admin.plans.partials.messaging-limits-fields', ['plan' => $plan])

  <hr>

  @include('admin.plans.partials.module-features-fields', ['plan' => $plan])

  <div class="tile-footer text-right px-0 pb-0 pt-3">
    <a class="btn btn-secondary" href="{{ route('admin.plans.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> {{ $submitLabel }}</button>
  </div>
</form>
