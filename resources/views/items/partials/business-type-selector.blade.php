@if($multiBusiness)
<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-store"></i>
  @if(!empty($branchFilterId))
    Choose which business type this item belongs to for <strong>{{ active_branch()?->name ?? 'this branch' }}</strong> — categories and units update automatically.
  @else
    Your shop has <strong>{{ count($businessTypes) }}</strong> business types. Choose which one this item belongs to — categories and units will update automatically.
  @endif
</div>
<div class="form-group">
  <label class="control-label">Business Type <span class="text-danger">*</span></label>
  <select class="form-control" name="business_type_key" id="itemBusinessTypeKey" required>
    <option value="">Select Business Type</option>
    @foreach($businessTypes as $type)
      <option value="{{ $type['key'] }}" {{ (string) old('business_type_key', $defaultBusinessTypeKey ?? '') === (string) $type['key'] ? 'selected' : '' }}>
        {{ $type['label'] }}
      </option>
    @endforeach
  </select>
  <small class="text-muted">Items are grouped by business type in stock and POS filters.</small>
</div>
@elseif(! empty($defaultBusinessTypeKey))
<input type="hidden" name="business_type_key" id="itemBusinessTypeKey" value="{{ $defaultBusinessTypeKey }}">
@elseif(count($businessTypes) === 0)
<div class="alert alert-warning py-2 mb-3">
  <i class="fa fa-exclamation-triangle"></i>
  No business type set up yet.
  <a href="{{ route('categories.index') }}" class="alert-link">Set up Categories</a> or
  <a href="{{ route('packagings.index') }}" class="alert-link">Packaging & Units</a> first.
</div>
@endif

@push('scripts')
<script>
(function () {
  const categoriesPayload = @json($categoriesPayload ?? []);
  const packagingsPayload = @json($packagingsPayload ?? []);
  const multiBusiness = @json($multiBusiness ?? false);
  const defaultBusinessTypeKey = @json($defaultBusinessTypeKey ?? null);

  function packagingOptionsHtml(typeKey, selectedId) {
    let html = '<option value="">Select Unit</option>';

    packagingsPayload
      .filter(function (pkg) {
        return typeKey && pkg.business_type_key === typeKey;
      })
      .forEach(function (pkg) {
        const selected = String(selectedId || '') === String(pkg.id) ? ' selected' : '';
        const name = String(pkg.name || '').replace(/"/g, '&quot;');
        html += '<option value="' + pkg.id + '" data-name="' + name + '"' + selected + '>' + pkg.name + '</option>';
      });

    return html;
  }

  function repopulatePackagingSelect(selectEl, typeKey, selectedId) {
    const $select = $(selectEl);
    const keep = selectedId !== undefined ? selectedId : $select.val();
    $select.html(packagingOptionsHtml(typeKey, keep));

    if (!$select.val() && keep) {
      $select.val('');
    }
  }

  function sellingPackageRowHtml(typeKey) {
    const editablePrices = String($('#sellingPackagesWrap').data('editable-prices') || '') === '1';
    const unitCol = editablePrices ? 'col-4' : 'col-7';
    const qtyCol = editablePrices ? 'col-3' : 'col-4';
    const priceColHtml = editablePrices
      ? '<div class="col-4 sell-pkg-price mb-2 mb-md-0">' +
          '<label class="control-label small d-md-none mb-1">Selling price (TZS)</label>' +
          '<span class="small text-muted d-none d-md-block mb-1">Selling price (TZS)</span>' +
          '<input type="number" class="form-control sell-pkg-selling-price" value="0" min="0" step="1" placeholder="0">' +
        '</div>'
      : '';

    return (
      '<div class="selling-package-row mt-2 pt-2" style="border-top:1px dashed #dee2e6;">' +
        '<div class="row align-items-end">' +
          '<div class="' + unitCol + ' mb-2 mb-md-0">' +
            '<label class="control-label small d-md-none mb-1">Sale unit</label>' +
            '<select class="form-control sell-pkg-select" required>' + packagingOptionsHtml(typeKey) + '</select>' +
          '</div>' +
          '<div class="' + qtyCol + ' sell-pkg-contains mb-2 mb-md-0">' +
            '<label class="control-label small sell-pkg-qty-label d-md-none mb-1">Pieces per sale unit</label>' +
            '<span class="small text-muted sell-pkg-qty-label d-none d-md-block mb-1">Pieces per sale unit</span>' +
            '<input type="number" class="form-control sell-pkg-qty text-center" value="1" min="1" step="1" placeholder="e.g. 12">' +
          '</div>' +
          priceColHtml +
          '<div class="col-1 text-right pl-0 sell-pkg-remove">' +
            '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selling-package" title="Remove"><i class="fa fa-times"></i></button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function updateSellingPackageTemplate(typeKey) {
    const tpl = document.getElementById('sellingPackageRowTemplate');
    if (!tpl) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.innerHTML = sellingPackageRowHtml(typeKey).trim();
    const row = wrap.firstElementChild;
    if (!row) {
      return;
    }

    while (tpl.content.firstChild) {
      tpl.content.removeChild(tpl.content.firstChild);
    }
    tpl.content.appendChild(row);
  }

  window.filterItemFormByBusinessType = function (typeKey) {
    const $catSelect = $('#itemCategorySelect');
    const currentCat = $catSelect.val();

    $catSelect.find('option:not(:first)').remove();

    categoriesPayload
      .filter(function (cat) {
        return typeKey && cat.business_type_key === typeKey;
      })
      .forEach(function (cat) {
        $catSelect.append('<option value="' + cat.id + '">' + cat.name + '</option>');
      });

    if (currentCat && $catSelect.find('option[value="' + currentCat + '"]').length) {
      $catSelect.val(currentCat);
    } else {
      $catSelect.val('');
    }

    repopulatePackagingSelect('#itemReceivingPackagingSelect', typeKey);
    $('#sellingPackagesList .sell-pkg-select').each(function () {
      repopulatePackagingSelect(this, typeKey);
    });
    updateSellingPackageTemplate(typeKey);

    if (typeof refreshReceivingQtyField === 'function') {
      refreshReceivingQtyField();
    }
    if (typeof refreshAllSellingPackageQtyRows === 'function') {
      refreshAllSellingPackageQtyRows();
    }
  };

  $(document).ready(function () {
    const $typeSelect = $('#itemBusinessTypeKey');

    if (!$typeSelect.length) {
      return;
    }

    function applyFilter() {
      const typeKey = $typeSelect.val() || defaultBusinessTypeKey || '';
      filterItemFormByBusinessType(typeKey);
    }

    if (multiBusiness) {
      $typeSelect.on('change', applyFilter);
    }

    applyFilter();
  });
})();
</script>
@endpush
