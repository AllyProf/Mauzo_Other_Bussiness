@php
    $permissionGroups = config('permissions.groups', []);
    $currentPermissions = $currentPermissions ?? [];
@endphp

<div class="mb-3 d-flex flex-wrap align-items-center role-preset-bar">
  <span class="text-muted small mr-2 mb-1">Quick presets:</span>
  @foreach(config('permissions.presets', []) as $presetName => $presetKeys)
    <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-1 role-preset-btn" data-preset="{{ $presetName }}">
      {{ $presetName }}
    </button>
  @endforeach
  <button type="button" class="btn btn-sm btn-outline-danger mb-1" id="clearAllPermissionsBtn">Clear all</button>
</div>

@foreach($permissionGroups as $groupName => $permissions)
  <div class="mb-4 permission-group" data-group="{{ $groupName }}">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 permission-group-header">
      <h6 class="text-uppercase text-muted mb-0">
        <i class="fa fa-folder-open-o"></i> {{ $groupName }}
      </h6>
      <div class="btn-group btn-group-sm permission-group-actions">
        <button type="button" class="btn btn-outline-primary select-group-btn" data-action="all">Select all</button>
        <button type="button" class="btn btn-outline-secondary select-group-btn" data-action="none">Clear</button>
      </div>
    </div>
    <div class="row">
      @foreach($permissions as $key => $label)
        <div class="col-12 col-md-6 mb-2">
          <div class="animated-checkbox">
            <label>
              <input
                type="checkbox"
                class="permission-checkbox"
                name="permissions[]"
                value="{{ $key }}"
                {{ in_array($key, $currentPermissions) ? 'checked' : '' }}
              >
              <span class="label-text">{{ $label }}</span>
            </label>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endforeach

@push('scripts')
<script type="text/javascript">
  (function () {
    const presets = @json(config('permissions.presets', []));

    function setPermissions(keys) {
      const keySet = new Set(keys || []);
      $('.permission-checkbox').each(function () {
        $(this).prop('checked', keySet.has($(this).val()));
      });
    }

    $('.role-preset-btn').on('click', function () {
      const name = $(this).data('preset');
      setPermissions(presets[name] || []);
    });

    $('#clearAllPermissionsBtn').on('click', function () {
      setPermissions([]);
    });

    $('.select-group-btn').on('click', function () {
      const $group = $(this).closest('.permission-group');
      const selectAll = $(this).data('action') === 'all';
      $group.find('.permission-checkbox').prop('checked', selectAll);
    });
  })();
</script>
@endpush
