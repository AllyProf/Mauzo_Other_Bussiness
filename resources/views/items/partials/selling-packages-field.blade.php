@php
  $pkgOptions = $packagingTypes ?? collect();
  $sellingRows = old('selling_packagings');

  if ($sellingRows === null && ! empty($item)) {
      $sellingRows = $item->packagings->map(fn ($p) => [
          'packaging_id' => $p->packaging_id,
          'quantity_per_unit' => $p->quantity_per_unit,
      ])->values()->all();
  }

  if (empty($sellingRows)) {
      $sellingRows = [['packaging_id' => old('selling_packaging_id', ''), 'quantity_per_unit' => 1]];
  }

  $multiSell = count($sellingRows) > 1;
@endphp

<div class="form-group mb-2" id="sellingPackagesWrap">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <label class="control-label mb-0">Selling Package (Sold as)</label>
    <button type="button" class="btn btn-sm btn-outline-primary py-0" id="addSellingPackageBtn" style="font-size:11px;">
      <i class="fa fa-plus"></i> Add
    </button>
  </div>

  <div id="sellingPackagesList">
    @foreach($sellingRows as $index => $row)
    <div class="selling-package-row {{ $index > 0 ? 'mt-2 pt-2' : '' }}" @if($index > 0) style="border-top:1px dashed #dee2e6;" @endif>
      <div class="row align-items-center">
        <div class="col-7 mb-1 mb-md-0">
          <select class="form-control sell-pkg-select" name="selling_packagings[{{ $index }}][packaging_id]" required>
            <option value="">Select Unit</option>
            @foreach($pkgOptions as $pkg)
              <option value="{{ $pkg->id }}" {{ (string) ($row['packaging_id'] ?? '') === (string) $pkg->id ? 'selected' : '' }}>
                {{ $pkg->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-4 sell-pkg-contains">
          <input type="number" class="form-control form-control-sm sell-pkg-qty text-center"
                 name="selling_packagings[{{ $index }}][quantity_per_unit]"
                 value="{{ $row['quantity_per_unit'] ?? 1 }}" min="1"
                 placeholder="Pcs" title="Pieces in stock per sale unit">
        </div>
        <div class="col-1 text-right pl-0 sell-pkg-remove {{ $multiSell ? '' : 'd-none' }}">
          <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selling-package" title="Remove">
            <i class="fa fa-times"></i>
          </button>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  <small class="text-muted d-block mt-1">
    <strong>Pcs</strong> = pieces deducted from stock when sold. <strong>Piece = 1</strong>, <strong>Box = how many pieces in one box</strong> (e.g. 20).
  </small>
  @error('selling_packagings') <div class="text-danger small">{{ $message }}</div> @enderror
</div>

<template id="sellingPackageRowTemplate">
  <div class="selling-package-row mt-2 pt-2" style="border-top:1px dashed #dee2e6;">
    <div class="row align-items-center">
      <div class="col-7 mb-1 mb-md-0">
        <select class="form-control sell-pkg-select" required>
          <option value="">Select Unit</option>
          @foreach($pkgOptions as $pkg)
            <option value="{{ $pkg->id }}">{{ $pkg->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-4 sell-pkg-contains">
        <input type="number" class="form-control form-control-sm sell-pkg-qty text-center" value="1" min="1" placeholder="Pcs" title="Pieces in stock per sale unit">
      </div>
      <div class="col-1 text-right pl-0 sell-pkg-remove">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selling-package" title="Remove">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>

@push('scripts')
<script>
(function () {
  function reindexSellingPackages() {
    $('#sellingPackagesList .selling-package-row').each(function (idx) {
      $(this).find('.sell-pkg-select').attr('name', 'selling_packagings[' + idx + '][packaging_id]');
      $(this).find('.sell-pkg-qty').attr('name', 'selling_packagings[' + idx + '][quantity_per_unit]');
    });
  }

  function refreshSellingPackageLayout() {
    const count = $('#sellingPackagesList .selling-package-row').length;
    const multi = count > 1;

    $('#sellingPackagesList .selling-package-row').each(function (idx) {
      const $row = $(this);
      $row.find('.sell-pkg-remove').toggleClass('d-none', !multi);
      $row.toggleClass('mt-2 pt-2', idx > 0);
      $row.css('border-top', idx > 0 ? '1px dashed #dee2e6' : '');
    });

    $('.remove-selling-package').prop('disabled', count <= 1);
    reindexSellingPackages();
  }

  $(document).ready(function () {
    $('#addSellingPackageBtn').on('click', function () {
      $('#sellingPackagesList').append($('#sellingPackageRowTemplate').html());
      refreshSellingPackageLayout();
    });

    $(document).on('click', '.remove-selling-package', function () {
      if ($('#sellingPackagesList .selling-package-row').length <= 1) return;
      $(this).closest('.selling-package-row').remove();
      refreshSellingPackageLayout();
    });

    refreshSellingPackageLayout();
  });
})();
</script>
@endpush
