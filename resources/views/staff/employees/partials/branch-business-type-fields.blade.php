<div id="employeeBusinessTypeBanner" class="mb-3"></div>
<div id="employeeBusinessTypeFields"></div>

@push('scripts')
<script>
jQuery(function($) {
  const typesByBranch = @json($importedTypesByBranch ?? []);
  const defaultTypeKeys = @json(array_values($defaultBusinessTypeKeys ?? []));
  const categoriesUrl = @json(route('categories.index'));
  let selectedTypeKeys = defaultTypeKeys.slice();

  function renderBusinessTypesForBranch(branchId) {
    const $banner = $('#employeeBusinessTypeBanner');
    const $fields = $('#employeeBusinessTypeFields');
    const types = typesByBranch[branchId] || typesByBranch[String(branchId)] || [];

    $banner.empty();
    $fields.empty();

    if (!branchId) {
      $banner.html(
        '<div class="alert alert-light border py-2 mb-0">' +
        '<i class="fa fa-map-marker"></i> Select a branch to load its business types.' +
        '</div>'
      );
      return;
    }

    if (types.length === 0) {
      $banner.html(
        '<div class="alert alert-warning py-2 mb-0">' +
        '<i class="fa fa-exclamation-triangle"></i> No business types imported for this branch yet. ' +
        '<a href="' + categoriesUrl + '">Import on Categories</a> first.' +
        '</div>'
      );
      return;
    }

    let badges = types.map(function(type) {
      return '<span class="badge badge-light ml-1">' + (type.label || type.key) + '</span>';
    }).join('');

    $banner.html(
      '<div class="alert alert-success py-2 mb-0">' +
      '<i class="fa fa-check-circle"></i> <strong>Business types for this branch:</strong>' + badges +
      '</div>'
    );

    selectedTypeKeys = selectedTypeKeys.filter(function(key) {
      return types.some(function(type) { return String(type.key) === String(key); });
    });

    if (types.length === 1) {
      $fields.html(
        '<input type="hidden" name="business_type_keys[]" value="' + types[0].key + '">' +
        '<div class="form-group">' +
          '<label class="control-label">Business</label>' +
          '<p class="form-control-plaintext mb-0"><span class="badge badge-light">' + (types[0].label || types[0].key) + '</span></p>' +
        '</div>'
      );
      return;
    }

    let checkboxes = types.map(function(type) {
      const checked = selectedTypeKeys.some(function(key) { return String(key) === String(type.key); }) ? ' checked' : '';
      return '<div class="custom-control custom-checkbox mb-2">' +
        '<input type="checkbox" class="custom-control-input business-type-checkbox" id="business_type_' + type.key + '" name="business_type_keys[]" value="' + type.key + '"' + checked + '>' +
        '<label class="custom-control-label" for="business_type_' + type.key + '">' + (type.label || type.key) + '</label>' +
        '</div>';
    }).join('');

    $fields.html(
      '<div class="form-group mb-0">' +
        '<label class="control-label">Business / Departments <span class="text-danger">*</span></label>' +
        '<div class="border rounded p-3 bg-light">' + checkboxes + '</div>' +
        '<small class="text-muted d-block mt-2">Select one or more businesses this staff member can sell and count stock for.</small>' +
        '<small class="text-danger d-none mt-1" id="businessTypeSelectionError">Select at least one business.</small>' +
      '</div>'
    );
  }

  $('#branchSelect').on('change', function() {
    selectedTypeKeys = [];
    renderBusinessTypesForBranch($(this).val());
  });

  $(document).on('change', '.business-type-checkbox', function() {
    selectedTypeKeys = $('.business-type-checkbox:checked').map(function() {
      return $(this).val();
    }).get();
    $('#businessTypeSelectionError').addClass('d-none');
  });

  $('#employeeCreateForm, #employeeEditForm').on('submit', function(e) {
    if ($('.business-type-checkbox').length && $('.business-type-checkbox:checked').length === 0) {
      e.preventDefault();
      $('#businessTypeSelectionError').removeClass('d-none');
      return false;
    }
  });

  renderBusinessTypesForBranch($('#branchSelect').val());
});
</script>
@endpush
