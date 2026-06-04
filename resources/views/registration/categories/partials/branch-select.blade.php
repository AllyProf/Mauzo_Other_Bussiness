@php
  $fieldId = $fieldId ?? 'categoryBranchSelect';
  $fieldName = $fieldName ?? 'branch_id';
  $selectedBranchId = old($fieldName, $defaultBranchId ?? '');
@endphp

@if(($writableBranches ?? collect())->isEmpty())
  <div class="alert alert-warning py-2 mb-3">
    <i class="fa fa-building"></i> No active branch is linked to this business yet.
    <a href="{{ route('branches.index') }}" class="alert-link">Register a branch</a> before importing categories.
  </div>
@elseif(($canPickBranch ?? false) && $writableBranches->count() > 1)
  <div class="form-group mb-3">
    <label class="control-label font-weight-bold" for="{{ $fieldId }}">
      Assign to Branch <span class="text-danger">*</span>
    </label>
    <select class="form-control @error($fieldName) is-invalid @enderror" name="{{ $fieldName }}" id="{{ $fieldId }}" required>
      <option value="">-- Select Branch --</option>
      @foreach($writableBranches as $branch)
        <option value="{{ $branch->id }}" {{ (string) $selectedBranchId === (string) $branch->id ? 'selected' : '' }}>
          {{ $branch->name }}@if($branch->is_default) (Default)@endif
        </option>
      @endforeach
    </select>
    <small class="text-muted">Categories for the selected business type will be registered under this branch.</small>
    @error($fieldName)<small class="text-danger d-block">{{ $message }}</small>@enderror
  </div>
@else
  @php $singleBranch = $writableBranches->first(); @endphp
  <input type="hidden" name="{{ $fieldName }}" value="{{ $singleBranch?->id }}">
  <div class="alert alert-light border py-2 mb-3">
    <i class="fa fa-map-marker"></i>
    Categories will be assigned to <strong>{{ $singleBranch?->name ?? 'your branch' }}</strong>.
  </div>
@endif
