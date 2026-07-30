@push('scripts')
<script>
(function () {
  window.isPiecePackagingName = function (name) {
    return /\b(piece|pieces|pcs|pc|each|single|unit)\b/i.test(String(name || ''));
  };

  window.packagingOptionName = function ($select) {
    const $opt = $select.find('option:selected');
    return $opt.data('name') || $opt.text() || 'unit';
  };

  window.selectedBasePackagingId = function () {
    return String($('#itemBasePackagingSelect').val() || '');
  };

  window.selectedBaseUnitName = function () {
    const $select = $('#itemBasePackagingSelect');
    if (!$select.length) {
      return 'piece';
    }

    const name = packagingOptionName($select).trim();
    return name || 'piece';
  };

  window.isBasePackagingSelected = function ($select) {
    const baseId = selectedBasePackagingId();
    const selectedId = String($select.val() || '');

    if (baseId && selectedId && baseId === selectedId) {
      return true;
    }

    // Fallback when base select is missing: treat classic piece names as base.
    return isPiecePackagingName(packagingOptionName($select));
  };

  window.refreshBaseUnitLabels = function () {
    const baseName = selectedBaseUnitName();
    const header = baseName + ' per sale unit';

    $('#sellingPackagesWrap [data-base-qty-header]').text(header);
    $('#sellingPackageRowTemplate .sell-pkg-qty-label').text(header);

    if (typeof refreshReceivingQtyField === 'function') {
      refreshReceivingQtyField();
    }
    if (typeof refreshAllSellingPackageQtyRows === 'function') {
      refreshAllSellingPackageQtyRows();
    }
  };

  window.refreshSellingPackageQtyRow = function ($row) {
    const $select = $row.find('.sell-pkg-select');
    const $input = $row.find('.sell-pkg-qty');

    if (!$select.length || !$input.length) {
      return;
    }

    const unitName = packagingOptionName($select);
    const baseName = selectedBaseUnitName();
    const isBase = isBasePackagingSelected($select);
    const labelText = isBase
      ? (baseName + ' (fixed)')
      : (baseName + ' in 1 ' + unitName);

    $row.find('.sell-pkg-qty-label').text(labelText);

    if (isBase) {
      $input.val(1).prop('readonly', true).removeClass('border-primary');
    } else {
      $input.prop('readonly', false).addClass('border-primary');
      if (parseInt($input.val(), 10) === 1 && !$input.data('userEdited')) {
        $input.attr('placeholder', 'e.g. 12');
      }
    }
  };

  window.refreshAllSellingPackageQtyRows = function () {
    $('#sellingPackagesList .selling-package-row').each(function () {
      refreshSellingPackageQtyRow($(this));
    });
  };

  $(document).ready(function () {
    $(document).on('change', '#itemBasePackagingSelect', refreshBaseUnitLabels);
    refreshBaseUnitLabels();
  });
})();
</script>
@endpush
