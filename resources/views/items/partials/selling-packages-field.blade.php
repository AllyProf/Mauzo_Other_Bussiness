@php
  $pkgOptions = $packagingTypes ?? collect();
  $editablePrices = (bool) ($editablePrices ?? false);
  $sellingRows = old('selling_packagings');

  if ($sellingRows === null && ! empty($item)) {
      $sellingRows = $item->packagings
          ->sortBy('quantity_per_unit')
          ->values()
          ->map(fn ($p) => [
              'packaging_id' => $p->packaging_id,
              'quantity_per_unit' => $p->quantity_per_unit,
              'selling_price' => $p->selling_price,
          ])
          ->all();
  }

  if (empty($sellingRows)) {
      $sellingRows = [['packaging_id' => old('selling_packaging_id', ''), 'quantity_per_unit' => 1, 'selling_price' => old('selling_price', 0)]];
  }

  $multiSell = count($sellingRows) > 1;
@endphp

<div class="form-group mb-2" id="sellingPackagesWrap" data-editable-prices="{{ $editablePrices ? '1' : '0' }}">
  @if($editablePrices)
  <style>
    .sell-pkg-selling-price { text-align: left; }
  </style>
  @endif
  <div class="d-flex justify-content-between align-items-center mb-1">
    <label class="control-label mb-0">Selling Package (Sold as)</label>
    <button type="button" class="btn btn-sm btn-outline-primary py-0" id="addSellingPackageBtn" style="font-size:11px;">
      <i class="fa fa-plus"></i> Add
    </button>
  </div>

  <div class="row small text-muted font-weight-bold mb-1 d-none d-md-flex">
    <div class="{{ $editablePrices ? 'col-4' : 'col-7' }}">Sale unit</div>
    <div class="{{ $editablePrices ? 'col-3' : 'col-4' }}">Pieces per sale unit</div>
    @if($editablePrices)
      <div class="col-4">Selling price (TZS)</div>
    @endif
    <div class="col-1"></div>
  </div>

  <div id="sellingPackagesList">
    @foreach($sellingRows as $index => $row)
    <div class="selling-package-row {{ $index > 0 ? 'mt-2 pt-2' : '' }}" @if($index > 0) style="border-top:1px dashed #dee2e6;" @endif>
      <div class="row align-items-end">
        <div class="{{ $editablePrices ? 'col-4' : 'col-7' }} mb-2 mb-md-0">
          <label class="control-label small d-md-none mb-1">Sale unit</label>
          <select class="form-control sell-pkg-select" name="selling_packagings[{{ $index }}][packaging_id]" required>
            <option value="">Select Unit</option>
            @foreach($pkgOptions as $pkg)
              <option value="{{ $pkg->id }}"
                      data-name="{{ $pkg->name }}"
                      {{ (string) ($row['packaging_id'] ?? '') === (string) $pkg->id ? 'selected' : '' }}>
                {{ $pkg->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="{{ $editablePrices ? 'col-3' : 'col-4' }} sell-pkg-contains mb-2 mb-md-0">
          <label class="control-label small sell-pkg-qty-label d-md-none mb-1">Pieces per sale unit</label>
          <span class="small text-muted sell-pkg-qty-label d-none d-md-block mb-1">Pieces per sale unit</span>
          <input type="number" class="form-control sell-pkg-qty text-center"
                 name="selling_packagings[{{ $index }}][quantity_per_unit]"
                 value="{{ $row['quantity_per_unit'] ?? 1 }}" min="1" step="1"
                 placeholder="e.g. 12" title="How many pieces leave stock when you sell 1 of this unit">
        </div>
        @if($editablePrices)
        <div class="col-4 sell-pkg-price mb-2 mb-md-0">
          <label class="control-label small d-md-none mb-1">Selling price (TZS)</label>
          <span class="small text-muted d-none d-md-block mb-1">Selling price (TZS)</span>
          <input type="number" class="form-control sell-pkg-selling-price"
                 name="selling_packagings[{{ $index }}][selling_price]"
                 value="{{ (int) round((float) old('selling_packagings.'.$index.'.selling_price', $row['selling_price'] ?? 0)) }}"
                 min="0" step="1" placeholder="0">
        </div>
        @endif
        <div class="col-1 text-right pl-0 sell-pkg-remove mb-2 mb-md-0 {{ $multiSell ? '' : 'd-none' }}">
          <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selling-package" title="Remove">
            <i class="fa fa-times"></i>
          </button>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  @error('selling_packagings') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
  @if($editablePrices)
    @error('selling_packagings.*.selling_price') <div class="text-danger small">{{ $message }}</div> @enderror
  @endif
</div>

<template id="sellingPackageRowTemplate">
  <div class="selling-package-row mt-2 pt-2" style="border-top:1px dashed #dee2e6;">
    <div class="row align-items-end">
      <div class="{{ $editablePrices ? 'col-4' : 'col-7' }} mb-2 mb-md-0">
        <label class="control-label small d-md-none mb-1">Sale unit</label>
        <select class="form-control sell-pkg-select" required>
          <option value="">Select Unit</option>
          @foreach($pkgOptions as $pkg)
            <option value="{{ $pkg->id }}" data-name="{{ $pkg->name }}">{{ $pkg->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="{{ $editablePrices ? 'col-3' : 'col-4' }} sell-pkg-contains mb-2 mb-md-0">
        <label class="control-label small sell-pkg-qty-label d-md-none mb-1">Pieces per sale unit</label>
        <span class="small text-muted sell-pkg-qty-label d-none d-md-block mb-1">Pieces per sale unit</span>
        <input type="number" class="form-control sell-pkg-qty text-center" value="1" min="1" step="1" placeholder="e.g. 12">
      </div>
      @if($editablePrices)
      <div class="col-4 sell-pkg-price mb-2 mb-md-0">
        <label class="control-label small d-md-none mb-1">Selling price (TZS)</label>
        <span class="small text-muted d-none d-md-block mb-1">Selling price (TZS)</span>
        <input type="number" class="form-control sell-pkg-selling-price" value="0" min="0" step="1" placeholder="0">
      </div>
      @endif
      <div class="col-1 text-right pl-0 sell-pkg-remove">
        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-selling-package" title="Remove">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>

@include('items.partials.packaging-qty-helpers')

@push('scripts')
<script>
(function () {
  function reindexSellingPackages() {
    $('#sellingPackagesList .selling-package-row').each(function (idx) {
      $(this).find('.sell-pkg-select').attr('name', 'selling_packagings[' + idx + '][packaging_id]');
      $(this).find('.sell-pkg-qty').attr('name', 'selling_packagings[' + idx + '][quantity_per_unit]');
      $(this).find('.sell-pkg-selling-price').attr('name', 'selling_packagings[' + idx + '][selling_price]');
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
    if (typeof refreshAllSellingPackageQtyRows === 'function') {
      refreshAllSellingPackageQtyRows();
    }
  }

  function appendSellingPackageRow() {
    const tpl = document.getElementById('sellingPackageRowTemplate');
    if (tpl && tpl.content && tpl.content.firstElementChild) {
      $('#sellingPackagesList').append($(tpl.content.firstElementChild.cloneNode(true)));
      return;
    }

    $('#sellingPackagesList').append($('#sellingPackageRowTemplate').html());
  }

  $(document).ready(function () {
    $('#addSellingPackageBtn').on('click', function () {
      appendSellingPackageRow();
      refreshSellingPackageLayout();
    });

    $(document).on('click', '.remove-selling-package', function () {
      if ($('#sellingPackagesList .selling-package-row').length <= 1) return;
      $(this).closest('.selling-package-row').remove();
      refreshSellingPackageLayout();
    });

    $(document).on('change', '.sell-pkg-select', function () {
      refreshSellingPackageQtyRow($(this).closest('.selling-package-row'));
    });

    $(document).on('input', '.sell-pkg-qty', function () {
      $(this).data('userEdited', 1);
    });

    refreshSellingPackageLayout();
  });
})();
</script>
@endpush
