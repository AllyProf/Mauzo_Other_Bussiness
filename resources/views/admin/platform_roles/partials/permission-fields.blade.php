@php
    $permissionGroups = config('platform_admin.permission_groups', []);
    $currentPermissions = $currentPermissions ?? [];
@endphp

@foreach($permissionGroups as $groupName => $permissions)
  <div class="mb-4 permission-group" data-group="{{ $groupName }}">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
      <h6 class="text-uppercase text-muted mb-0"><i class="fa fa-folder-open-o"></i> {{ $groupName }}</h6>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-primary select-group-btn" data-action="all">Select all</button>
        <button type="button" class="btn btn-outline-secondary select-group-btn" data-action="none">Clear</button>
      </div>
    </div>
    <div class="row">
      @foreach($permissions as $key => $label)
      <div class="col-md-6 mb-2">
        <div class="animated-checkbox">
          <label>
            <input type="checkbox" class="permission-checkbox" name="permissions[]" value="{{ $key }}" {{ in_array($key, $currentPermissions) ? 'checked' : '' }}>
            <span class="label-text">{{ $label }}</span>
          </label>
        </div>
      </div>
      @endforeach
    </div>
  </div>
@endforeach

<button type="button" class="btn btn-sm btn-outline-danger mb-2" id="clearAllPermissionsBtn">Clear all permissions</button>

<script>
jQuery(function($) {
  $('#clearAllPermissionsBtn').on('click', function() {
    $('.permission-checkbox').prop('checked', false);
  });

  $('.select-group-btn').on('click', function() {
    const selectAll = $(this).data('action') === 'all';
    $(this).closest('.permission-group').find('.permission-checkbox').prop('checked', selectAll);
  });
});
</script>
