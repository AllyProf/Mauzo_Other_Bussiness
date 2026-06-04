@php
  $receivingQty = old('units_per_receiving_pack', isset($item) ? ($item->units_per_receiving_pack ?? 1) : 1);
  $receivingPackagingId = old('receiving_packaging_id', isset($item) ? ($item->receiving_packaging_id ?? '') : '');
@endphp

<div class="form-group">
  <label class="control-label">Receiving Package (Purchased as)</label>
  <div class="row align-items-end">
    <div class="col-md-7 mb-2 mb-md-0">
      <select class="form-control" name="receiving_packaging_id" id="itemReceivingPackagingSelect" required>
        <option value="">Select Unit</option>
        @foreach($packagingTypes as $pkg)
          <option value="{{ $pkg->id }}" data-name="{{ $pkg->name }}" {{ (string) $receivingPackagingId === (string) $pkg->id ? 'selected' : '' }}>
            {{ $pkg->name }}
          </option>
        @endforeach
      </select>
      <small class="text-muted">Unit used when buying from supplier (e.g. Box, Carton, Dozen)</small>
    </div>
    <div class="col-md-5" id="receivingQtyWrap">
      <label class="control-label small mb-1" id="receivingQtyLabel">Pieces per purchase unit</label>
      <input type="number"
             class="form-control @error('units_per_receiving_pack') is-invalid @enderror"
             name="units_per_receiving_pack"
             id="receivingQtyInput"
             value="{{ $receivingQty }}"
             min="1"
             step="1"
             required
             placeholder="e.g. 12">
      <small class="text-muted" id="receivingQtyHint">How many pieces in 1 purchase unit</small>
      @error('units_per_receiving_pack') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  function isPieceUnitName(name) {
    return /\b(piece|pieces|pcs|pc|each|single|unit)\b/i.test(String(name || ''));
  }

  window.refreshReceivingQtyField = function () {
    const $select = $('#itemReceivingPackagingSelect');
    const $input = $('#receivingQtyInput');
    const $label = $('#receivingQtyLabel');
    const $hint = $('#receivingQtyHint');

    if (!$select.length || !$input.length) {
      return;
    }

    const selected = $select.find('option:selected');
    const unitName = selected.data('name') || selected.text() || 'unit';
    const isPiece = isPieceUnitName(unitName);

    if (isPiece) {
      $input.val(1).prop('readonly', true).removeClass('border-primary');
      $label.text('Pieces per purchase unit');
      $hint.text('Single piece — always 1 piece per unit.');
    } else {
      $input.prop('readonly', false).addClass('border-primary');
      $label.text('Pieces in 1 ' + unitName);
      $hint.text('Example: if 1 ' + unitName + ' has 12 pieces, enter 12.');
      if (parseInt($input.val(), 10) === 1 && !$input.data('userEdited')) {
        $input.attr('placeholder', 'e.g. 12');
      }
    }
  };

  $(document).ready(function () {
    $('#receivingQtyInput').on('input', function () {
      $(this).data('userEdited', 1);
    });

    $('#itemReceivingPackagingSelect').on('change', refreshReceivingQtyField);
    refreshReceivingQtyField();
  });
})();
</script>
@endpush
