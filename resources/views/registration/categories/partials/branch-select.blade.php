@php
  $fieldId = $fieldId ?? 'categoryBranchSelect';
  $fieldName = $fieldName ?? 'branch_id';
  $selectedBranchId = old($fieldName, $defaultBranchId ?? '');
@endphp

@if(($writableBranches ?? collect())->isEmpty())
  <div class="alert alert-warning py-2 mb-3">
    <i class="fa fa-building"></i> {{ __('categories.branch.no_branch') }}
    <a href="{{ route('branches.index') }}" class="alert-link">{{ __('categories.branch.register_branch') }}</a> {{ __('categories.branch.before_import') }}
  </div>
@elseif(($canPickBranch ?? false) && $writableBranches->count() > 1)
  <div class="form-group mb-3">
    <label class="control-label font-weight-bold" for="{{ $fieldId }}">
      {{ __('categories.branch.assign_to') }} <span class="text-danger">*</span>
    </label>
    <select class="form-control @error($fieldName) is-invalid @enderror" name="{{ $fieldName }}" id="{{ $fieldId }}" required>
      <option value="">{{ __('categories.branch.select_branch') }}</option>
      @foreach($writableBranches as $branch)
        <option value="{{ $branch->id }}" {{ (string) $selectedBranchId === (string) $branch->id ? 'selected' : '' }}>
          {{ $branch->name }}@if($branch->is_default) ({{ __('common.default') }})@endif
        </option>
      @endforeach
    </select>
    <small class="text-muted">{{ __('categories.branch.assign_hint') }}</small>
    @error($fieldName)<small class="text-danger d-block">{{ $message }}</small>@enderror
  </div>
@else
  @php $singleBranch = $writableBranches->first(); @endphp
  <input type="hidden" name="{{ $fieldName }}" value="{{ $singleBranch?->id }}">
  <div class="alert alert-light border py-2 mb-3">
    <i class="fa fa-map-marker"></i>
    {!! __('categories.branch.assigned_to', ['branch' => '<strong>'.e($singleBranch?->name ?? __('categories.branch.your_branch')).'</strong>']) !!}
  </div>
@endif
