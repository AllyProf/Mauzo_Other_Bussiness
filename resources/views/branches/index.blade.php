@extends('layouts.app')

@section('title', 'Branches')

@section('content')
@php
  $maxBranches = $business->maxBranchesAllowed();
  $currentCount = $branches->count();
  $canAddBranch = $maxBranches === null || $currentCount < $maxBranches;

  $formatPhoneLocal = function (?string $phone) {
      if (! $phone) {
          return '';
      }
      $value = preg_replace('/\s+/', '', $phone);
      if (str_starts_with($value, '+255')) {
          $value = substr($value, 4);
      } elseif (str_starts_with($value, '255')) {
          $value = substr($value, 3);
      }
      return ltrim($value, '0');
  };
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-building"></i> Branches</h1>
    <p>Register your shop locations and branch leader details before assigning employees</p>
  </div>
</div>

<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-credit-card"></i>
  Your <strong>{{ $business->plan->name ?? 'plan' }}</strong> allows
  <strong>{{ $business->branchesLimitLabel() }}</strong> branch(es).
  <span class="ml-1">Registered: <strong>{{ $currentCount }}</strong></span>
</div>

<div class="row">
  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title">Register Branch</h3>
      @if($canAddBranch)
      <form action="{{ route('branches.store') }}" method="POST" id="branchCreateForm">
        @csrf
        <div class="form-group">
          <label class="control-label">Branch Name <span class="text-danger">*</span></label>
          <input class="form-control" type="text" name="name" placeholder="e.g. Arusha Main Shop" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
          <label class="control-label">Location</label>
          <input class="form-control" type="text" name="location" placeholder="e.g. Arusha City Centre" value="{{ old('location') }}">
          <small class="text-muted">City, area, or landmark for this branch.</small>
        </div>
        <div class="form-group">
          <label class="control-label">Address</label>
          <textarea class="form-control" name="address" rows="2" placeholder="Street, building, plot number">{{ old('address') }}</textarea>
        </div>

        <hr>
        <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-user"></i> Branch Leader</h6>
        <div class="form-group">
          <label class="control-label">Leader Name</label>
          <input class="form-control" type="text" name="leader_name" placeholder="Branch manager name" value="{{ old('leader_name') }}">
        </div>
        <div class="form-group">
          <label class="control-label">Leader Phone</label>
          <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
            <input type="tel" class="form-control branch-phone-local" placeholder="712345678" maxlength="10" inputmode="numeric" value="{{ $formatPhoneLocal(old('leader_phone')) }}">
          </div>
          <input type="hidden" name="leader_phone" class="branch-phone-hidden" value="{{ old('leader_phone') }}">
        </div>
        <div class="form-group">
          <label class="control-label">Leader Email</label>
          <input class="form-control" type="email" name="leader_email" placeholder="leader@example.com" value="{{ old('leader_email') }}">
        </div>

        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Register Branch</button>
      </form>
      @else
      <div class="alert alert-warning mb-0">
        <i class="fa fa-lock"></i> Branch limit reached for your plan.
        <p class="small mb-0 mt-2">Contact the platform administrator to upgrade and add more branches.</p>
      </div>
      @endif
    </div>
  </div>

  <div class="col-md-8">
    <div class="tile">
      <h3 class="tile-title">Your Branches</h3>
      <div class="table-responsive">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Branch</th>
              <th>Location</th>
              <th>Branch Leader</th>
              <th>Staff</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($branches as $branch)
              <tr>
                <td>
                  <strong>{{ $branch->name }}</strong>
                  @if($branch->is_default)
                    <span class="badge badge-primary ml-1">Default</span>
                  @endif
                  @if($branch->address)
                    <div class="small text-muted">{{ $branch->address }}</div>
                  @endif
                </td>
                <td>{{ $branch->location ?: '—' }}</td>
                <td>
                  @if($branch->leader_name || $branch->leader_phone || $branch->leader_email)
                    @if($branch->leader_name)
                      <div><strong>{{ $branch->leader_name }}</strong></div>
                    @endif
                    @if($branch->leader_phone)
                      <div class="small">{{ $branch->leader_phone }}</div>
                    @endif
                    @if($branch->leader_email)
                      <div class="small text-muted">{{ $branch->leader_email }}</div>
                    @endif
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td><span class="badge badge-info">{{ $branch->users()->where('role', 'staff')->count() }}</span></td>
                <td>
                  @if($branch->is_active)
                    <span class="badge badge-success">Active</span>
                  @else
                    <span class="badge badge-secondary">Inactive</span>
                  @endif
                </td>
                <td class="text-nowrap">
                  <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#editBranchModal{{ $branch->id }}">
                    <i class="fa fa-edit"></i>
                  </button>
                  @if(! $branch->is_default)
                  <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete branch {{ $branch->name }}?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                  </form>
                  @endif
                </td>
              </tr>

              <div class="modal fade" id="editBranchModal{{ $branch->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                  <div class="modal-content">
                    <form action="{{ route('branches.update', $branch) }}" method="POST" class="branch-edit-form">
                      @csrf @method('PUT')
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Branch</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                      </div>
                      <div class="modal-body">
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Branch Name</label>
                              <input class="form-control" type="text" name="name" value="{{ $branch->name }}" required>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Location</label>
                              <input class="form-control" type="text" name="location" value="{{ $branch->location }}" placeholder="City, area, landmark">
                            </div>
                          </div>
                        </div>
                        <div class="form-group">
                          <label>Address</label>
                          <textarea class="form-control" name="address" rows="2">{{ $branch->address }}</textarea>
                        </div>

                        <hr>
                        <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-user"></i> Branch Leader</h6>
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Leader Name</label>
                              <input class="form-control" type="text" name="leader_name" value="{{ $branch->leader_name }}">
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Leader Email</label>
                              <input class="form-control" type="email" name="leader_email" value="{{ $branch->leader_email }}">
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Leader Phone</label>
                              <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
                                <input type="tel" class="form-control branch-phone-local" value="{{ $formatPhoneLocal($branch->leader_phone) }}" placeholder="712345678" maxlength="10" inputmode="numeric">
                              </div>
                              <input type="hidden" name="leader_phone" class="branch-phone-hidden" value="{{ $branch->leader_phone }}">
                            </div>
                          </div>
                        </div>

                        <div class="form-group mb-0">
                          <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="branchActive{{ $branch->id }}" name="is_active" value="1" {{ $branch->is_active ? 'checked' : '' }}>
                            <label class="custom-control-label" for="branchActive{{ $branch->id }}">Branch is active</label>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No branches registered yet. Add your first branch on the left.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  function formatPhoneForSave(local) {
    const digits = String(local || '').replace(/\D/g, '').replace(/^0+/, '');
    return digits ? '+255' + digits : '';
  }

  function syncPhonePair($local, $hidden) {
    $hidden.val(formatPhoneForSave($local.val()));
  }

  function syncFormPhones($form) {
    $form.find('.branch-phone-local').each(function() {
      const $local = $(this);
      const $hidden = $local.closest('.form-group').find('.branch-phone-hidden').first();
      if ($hidden.length) {
        syncPhonePair($local, $hidden);
      }
    });
  }

  $('.branch-phone-local').on('input', function() {
    const $local = $(this);
    const $hidden = $local.closest('.form-group').find('.branch-phone-hidden').first();
    syncPhonePair($local, $hidden);
  });

  $('#branchCreateForm, .branch-edit-form').on('submit', function() {
    syncFormPhones($(this));
  });

  $('#branchCreateForm .branch-phone-local').each(function() {
    const $local = $(this);
    const $hidden = $local.closest('.form-group').find('.branch-phone-hidden').first();
    syncPhonePair($local, $hidden);
  });
});
</script>
@endsection
