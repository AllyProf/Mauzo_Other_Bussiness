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

  window.refreshSellingPackageQtyRow = function ($row) {
    const $select = $row.find('.sell-pkg-select');
    const $input = $row.find('.sell-pkg-qty');

    if (!$select.length || !$input.length) {
      return;
    }

    const unitName = packagingOptionName($select);
    const isPiece = isPiecePackagingName(unitName);
    const labelText = isPiece ? 'Pcs (fixed)' : 'Pcs in 1 ' + unitName;

    $row.find('.sell-pkg-qty-label').text(labelText);

    if (isPiece) {
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
})();
</script>
@endpush
