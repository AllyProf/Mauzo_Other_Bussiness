@extends('layouts.app')

@section('title', 'New Stock Receipt')

@section('styles')
<style>
  .truncate-1 { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .tile { border-radius: 12px; transition: transform 0.2s; }
  .smallest { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; }
  .opacity-75 { opacity: 0.75; }

  .receipt-summary-box {
    background: linear-gradient(135deg, #940000 0%, #d40000 100%);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(148, 0, 0, 0.2);
    color: white;
    padding: 24px;
    position: relative;
    overflow: hidden;
  }
  .receipt-summary-box::after {
    content: "";
    position: absolute;
    top: -20px;
    right: -20px;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
  }
  .summary-card-light { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; }
  .summary-card-dark { background: rgba(0, 0, 0, 0.15); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; }

  #itemsTable {
    table-layout: fixed;
    width: 100%;
  }
  #itemsTable thead th {
    background: #f8f9fa;
    border: 0;
    color: #6c757d;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 12px 10px;
    vertical-align: middle;
  }
  #itemsTable tbody td {
    vertical-align: middle !important;
    padding: 10px 8px;
  }
  #itemsTable tbody tr { transition: background 0.2s; }
  #itemsTable tbody tr:hover { background: #fef8f8; }
  #itemsTable .form-control-sm {
    height: calc(1.5em + 0.75rem + 2px);
    font-size: 0.875rem;
  }
  #itemsTable input[type="number"].item-pkg {
    text-align: center !important;
    max-width: 96px;
    margin: 0 auto;
  }
  #itemsTable input[type="number"].item-buy-price,
  #itemsTable input[type="number"].item-discount-amount {
    text-align: left !important;
  }
  #itemsTable td.col-money,
  #itemsTable th.col-money {
    text-align: left;
  }
  .open-retail-modal {
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    color: #155724 !important;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .open-retail-modal:hover {
    background-color: #c3e6cb !important;
    color: #155724 !important;
  }
  #retailPriceModal .rpm-price-input {
    text-align: left !important;
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    color: #155724;
    font-weight: 600;
  }
  .receiving-remains {
    display: inline-block;
    min-width: 48px;
    padding: 5px 8px;
    background: #d4edda;
    border: 1px solid #28a745;
    border-radius: 4px;
    color: #155724;
    font-weight: 700;
    font-size: 0.85rem;
    text-align: center;
  }
  .item-meta-line {
    font-size: 0.72rem;
    color: #6c757d;
    line-height: 1.3;
    margin-top: 4px;
  }
  .item-meta-line .toggle-price-mode {
    color: #007bff;
    cursor: pointer;
    text-decoration: underline;
  }

  .product-cell {
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
  .receiving-price-field {
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    color: #155724;
    font-weight: 600;
  }
  .receiving-price-field:focus {
    border-color: #940000 !important;
    box-shadow: 0 0 0 3px rgba(148, 0, 0, 0.1) !important;
  }
  .item-pkg {
    border-color: #940000 !important;
    background: #fff9f9;
    font-weight: 700;
  }
  .cursor-pointer { cursor: pointer; }

  .search-dropdown-menu {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    background: #fff;
    max-height: 320px;
    overflow-y: auto;
    position: absolute;
    width: 100%;
    z-index: 1050;
    margin-top: 4px;
  }
  .search-item-option { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
  .search-item-option:hover { background: #fef8f8; border-left: 3px solid #940000; }
  .row-flash { animation: flashRow 1.5s ease-out; }
  @keyframes flashRow {
    0% { background-color: rgba(148, 0, 0, 0.12); }
    100% { background-color: transparent; }
  }
</style>
@endsection

@section('content')
@php
  $multiBusiness = count($businessTypes ?? []) > 1;
  $multiBranch = ($branches ?? collect())->count() > 1;
  $receiptCol = $multiBusiness ? 3 : ($multiBranch ? 3 : 4);
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-download text-success"></i> Stock Reception</h1>
    <p>Transfer product from Supplier to Warehouse stock</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('receivings.index') }}">Receiving</a></li>
    <li class="breadcrumb-item active">New Stock-In</li>
  </ul>
</div>

@if($multiBusiness)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>Multi-department shop:</strong> choose branch, then business type, then pick a category to load items.
</div>
@endif

@if($multiBranch)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-map-marker text-primary"></i>
  Select the <strong>branch</strong> receiving this stock. It controls where the stock-in appears in branch reports.
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <form method="POST" action="{{ route('receivings.store') }}" id="stockReceiptForm">
        @csrf
        @if(!$multiBranch && $defaultBranchId)
          <input type="hidden" name="branch_id" id="branch_id" value="{{ $defaultBranchId }}">
        @endif
      <div class="row">
        {{-- Main column --}}
        <div class="col-md-9">
          <div class="tile shadow-sm border-0 mb-3">
            <div class="border-bottom pb-3 mb-4">
              <h3 class="tile-title mb-0"><i class="fa fa-truck mr-2 text-primary"></i> Receipt Details</h3>
            </div>

            <div class="row">
              @if($multiBranch)
              <div class="col-md-{{ $receiptCol }}">
                <div class="form-group">
                  <label class="font-weight-bold small text-uppercase">Receiving Branch *</label>
                  <select class="form-control" name="branch_id" id="branch_id" required>
                    @foreach($branches as $branch)
                      <option value="{{ $branch->id }}" {{ (string) old('branch_id', $defaultBranchId) === (string) $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                      </option>
                    @endforeach
                  </select>
                  <small class="text-muted">Stock-in will appear under this branch in history.</small>
                </div>
              </div>
              @endif
              <div class="col-md-{{ $receiptCol }}">
            <div class="form-group">
                  <label class="font-weight-bold small text-uppercase">Supplier / Distributor *</label>
                  <select class="form-control" name="supplier_id" id="supplier_id" required>
                <option value="">-- Select Supplier --</option>
                @foreach($suppliers as $supplier)
                      <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
              <div class="col-md-{{ $receiptCol }}">
                <div class="form-group">
                  <label class="font-weight-bold small text-uppercase">Receiving Date *</label>
                  <input type="date" class="form-control" name="received_date" id="received_date" value="{{ date('Y-m-d') }}" required>
                </div>
              </div>
              @if($multiBusiness)
              <div class="col-md-3" id="business-type-field">
            <div class="form-group">
                  <label class="font-weight-bold small text-uppercase">Business *</label>
                  <select class="form-control" id="business-type-selector" required>
                    <option value="">-- Select Business --</option>
                    @foreach($businessTypes as $type)
                      <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                    @endforeach
                  </select>
                  <small class="text-muted d-block">Business types for the selected branch.</small>
            </div>
          </div>
              @else
              <div class="col-md-3 d-none" id="business-type-field">
            <div class="form-group">
                  <label class="font-weight-bold small text-uppercase">Business *</label>
                  <select class="form-control" id="business-type-selector">
                    <option value="">-- Select Business --</option>
                  </select>
                  <small class="text-muted d-block">Business types for the selected branch.</small>
            </div>
          </div>
              @endif
              <div class="col-md-{{ $multiBusiness ? 3 : $receiptCol }}">
            <div class="form-group">
                  <label class="font-weight-bold small text-uppercase text-primary">Load Inventory By Category</label>
                  <select class="form-control border-primary" id="category_filter" style="border-width: 2px;">
                    <option value="">-- Choose Category --</option>
                @foreach($categories as $cat)
                      <option value="{{ $cat->id }}"
                              data-business-type="{{ $cat->source_business_type_key ?: 'other' }}"
                              data-branch-id="{{ $cat->branch_id }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

            <div class="form-group position-relative mb-0">
              <label class="font-weight-bold small text-uppercase text-muted"><i class="fa fa-search"></i> Quick Search Item</label>
              <input type="text" id="item-search-input" class="form-control" placeholder="Type item name to add..." autocomplete="off">
              <div id="search-results-dropdown" class="search-dropdown-menu" style="display: none;"></div>
            </div>
          </div>

          <div class="tile shadow-sm border-0 p-0 overflow-hidden">
            <div class="bg-dark p-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0 text-white"><i class="fa fa-list-ul mr-2 text-success"></i> Stock Entry List</h5>
              <span id="items_badge" class="badge badge-pill badge-primary">0 Items</span>
            </div>
        <div class="table-responsive">
              <table class="table table-hover table-bordered mb-0" id="itemsTable">
                <thead class="bg-light">
                  <tr>
                    <th class="border-top-0 px-3 py-2" style="width:22%;">PRODUCT</th>
                    <th class="border-top-0 px-2 py-2 text-center" style="width:11%;">QTY</th>
                    <th class="border-top-0 px-2 py-2 col-money" style="width:17%;">BUYING COST</th>
                    <th class="border-top-0 px-2 py-2 col-money" style="width:17%;">SELL PRICE</th>
                    <th class="border-top-0 px-2 py-2 col-money" style="width:21%;">DISCOUNT</th>
                    <th class="border-top-0 px-2 py-2 text-center" style="width:7%;"></th>
              </tr>
            </thead>
                <tbody id="itemsTableBody">
                  <tr id="emptyTableMsg">
                    <td colspan="6" class="text-center py-5 text-muted">
                      <i class="fa fa-cubes fa-3x mb-3 d-block opacity-25"></i>
                      Select a category above or search to load products into this receipt.
                </td>
              </tr>
            </tbody>
          </table>
            </div>
          </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-md-3">
          <div class="sticky-top" style="top: 20px;">
            <div class="tile shadow-lg border-0 receipt-summary-box mb-3">
              <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                  <h5 class="mb-0 font-weight-bold">Summary</h5>
                  <small class="opacity-75">Valuation & Profitability</small>
                </div>
                <span class="badge badge-light badge-pill px-3 py-1" id="summary_items_badge">0 Items</span>
              </div>

              <div class="row no-gutters mb-4">
                <div class="col-6 pr-2">
                  <div class="summary-card-light p-2 text-center h-100">
                    <small class="smallest d-block opacity-75">Recv. Units</small>
                    <span class="h4 font-weight-bold mb-0" id="summ_packages">0</span>
                  </div>
                </div>
                <div class="col-6 pl-2">
                  <div class="summary-card-light p-2 text-center h-100">
                    <small class="smallest d-block opacity-75">Total Pieces</small>
                    <span class="h4 font-weight-bold mb-0" id="summ_units">0</span>
                  </div>
                </div>
              </div>

              <div class="mb-2 d-flex justify-content-between smallest px-1">
                <span class="opacity-75">Gross Purchase</span>
                <span id="summ_gross">0</span>
              </div>
              <div class="mb-3 d-flex justify-content-between smallest text-warning font-weight-bold px-1">
                <span>Discount Applied (-)</span>
                <span id="summ_discount">0</span>
              </div>

              <div class="summary-card-dark p-3 text-center mb-4 border-left border-warning" style="border-left-width: 4px !important;">
                <span class="smallest opacity-75 d-block mb-1">Total Buying Cost</span>
                <div class="h3 font-weight-bold mb-0 text-white" id="summ_cost">0</div>
                <div class="smallest text-white-50 mt-1">Avg Cost: <span id="summ_unit_cost" class="text-white">0</span> / piece</div>
              </div>

              <div class="summary-card-light p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="smallest opacity-75">Expected Selling</span>
                  <span class="font-weight-bold" id="summ_selling">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top border-white-10">
                  <span class="smallest font-weight-bold text-warning">Est. Net Profit</span>
                  <span class="h5 mb-0 font-weight-bold" id="summ_profit">0</span>
                </div>
              </div>

              <div class="row no-gutters">
                <div class="col-6 pr-1">
                  <div class="summary-card-dark p-2 text-center">
                    <small class="smallest d-block opacity-50 mb-1">Margin</small>
                    <span class="font-weight-bold h5 mb-0" id="summ_margin">0%</span>
                  </div>
                </div>
                <div class="col-6 pl-1">
                  <div class="summary-card-dark p-2 text-center">
                    <small class="smallest d-block opacity-50 mb-1">ROI</small>
                    <span class="font-weight-bold h5 mb-0" id="summ_roi">0%</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="tile shadow-sm border-0 p-3 mb-3">
              <button type="submit" class="btn btn-success btn-block btn-lg shadow rounded-pill py-3 font-weight-bold" id="submitBtn" disabled>
                <i class="fa fa-check-circle mr-2"></i> POST RECEIPT
          </button>
              <p class="text-center small text-muted mt-3 mb-0">Stock will be added to inventory immediately upon posting.</p>
              <a href="{{ route('receivings.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                <i class="fa fa-times-circle"></i> Cancel
              </a>
            </div>
          </div>
        </div>
        </div>
      </form>
  </div>
</div>

<div class="modal fade" id="retailPriceModal" tabindex="-1" role="dialog" aria-labelledby="retailPriceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title font-weight-bold" id="retailPriceModalLabel">
          <i class="fa fa-tag text-success mr-2"></i> Sell Price
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small mb-3">Set the selling price for <strong id="rpmItemName"></strong></p>
        <div id="retailPriceModalBody"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success px-4" id="retailPriceModalSave">
          <i class="fa fa-check mr-1"></i> Save Prices
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const itemsByCategory = @json($itemsByCategory);
  const importedTypesByBranch = @json($importedTypesByBranch ?? []);
  const categoryBranchMap = @json($categoryBranchMap ?? []);
  const fixedBranchId = @json($multiBranch ? null : ($defaultBranchId ?? null));
  let hasMultipleBusinessTypes = @json($multiBusiness);

  $(document).ready(function () {
    let receiptItems = [];
    let allFlatItems = [];

    Object.keys(itemsByCategory).forEach(catId => {
      const branchId = categoryBranchMap[catId] ?? categoryBranchMap[String(catId)] ?? null;
      itemsByCategory[catId].forEach(item => {
        allFlatItems.push({ ...item, categoryId: catId, branchId: branchId });
      });
    });

    const categoryFilter = $('#category_filter');
    const itemsTableBody = $('#itemsTableBody');
    const submitBtn = $('#submitBtn');
    const itemSearchInput = $('#item-search-input');
    const searchDropdown = $('#search-results-dropdown');

    function cleanNum(val) {
      if (!val) return 0;
      if (typeof val === 'string') val = val.replace(/,/g, '');
      const n = parseFloat(val);
      return isNaN(n) ? 0 : n;
    }

    function resolvePackagingRetailPrice(item, pkg) {
      if (item.selling_prices && Object.prototype.hasOwnProperty.call(item.selling_prices, pkg.id)) {
        return cleanNum(item.selling_prices[pkg.id]);
      }

      const packagings = item.packagings || [];
      if (packagings.length <= 1 && cleanNum(item.selling_price_per_unit) > 0) {
        return cleanNum(item.selling_price_per_unit);
      }

      return cleanNum(pkg.selling_price);
    }

    function resolveSellPerPiece(item) {
      const packagings = item.packagings || [];

      if (packagings.length === 0) {
        return cleanNum(item.selling_price_per_unit);
      }

      const primary = packagings[0];
      const pkgSell = resolvePackagingRetailPrice(item, primary);

      return pkgSell / Math.max(1, primary.quantity_per_unit || 1);
    }

    function formatStockQty(value) {
      const num = parseFloat(value) || 0;
      return Number.isInteger(num) ? String(num) : num.toFixed(2);
    }

    function emptyTableHtml() {
      return `<tr id="emptyTableMsg">
        <td colspan="6" class="text-center py-5 text-muted">
          <i class="fa fa-cubes fa-3x mb-3 d-block opacity-25"></i>
          Select a category above or search to load products into this receipt.
        </td>
      </tr>`;
    }

    function getSelectedBranchId() {
      const branchVal = $('#branch_id').val();
      if (branchVal) return String(branchVal);
      if (fixedBranchId) return String(fixedBranchId);
      return '';
    }

    function branchBusinessTypes() {
      const branchId = getSelectedBranchId();
      if (!branchId) return [];
      return importedTypesByBranch[branchId] || importedTypesByBranch[Number(branchId)] || [];
    }

    function rebuildBusinessTypeSelector() {
      const types = branchBusinessTypes();
      const $field = $('#business-type-field');
      const $selector = $('#business-type-selector');
      const previous = $selector.val();

      hasMultipleBusinessTypes = types.length > 1;
      $selector.empty().append('<option value="">-- Select Business --</option>');

      types.forEach(function (type) {
        $selector.append(
          $('<option></option>').val(type.key).text(type.label || type.key)
        );
      });

      if (types.length === 1) {
        $selector.val(types[0].key);
      } else if (previous && types.some(function (type) { return String(type.key) === String(previous); })) {
        $selector.val(previous);
      } else {
        $selector.val('');
      }

      if (types.length === 0) {
        $field.addClass('d-none');
        $selector.prop('required', false);
      } else if (types.length === 1) {
        $field.addClass('d-none');
        $selector.prop('required', false);
      } else {
        $field.removeClass('d-none');
        $selector.prop('required', true);
      }
    }

    function syncCategoryOptions() {
      const branchId = getSelectedBranchId();
      const businessKey = hasMultipleBusinessTypes ? ($('#business-type-selector').val() || '') : '';
      const $category = categoryFilter;
      const previous = $category.val();

      $category.find('option').each(function () {
        const $option = $(this);
        if (!$option.val()) { $option.show(); return; }

        const optionBranchId = String($option.attr('data-branch-id') || '');
        const branchMatches = !branchId || optionBranchId === branchId;
        const businessMatches = !hasMultipleBusinessTypes || !businessKey
          || String($option.attr('data-business-type')) === String(businessKey);
        $option.toggle(branchMatches && businessMatches);
      });

      const needsBusinessChoice = hasMultipleBusinessTypes && branchBusinessTypes().length > 1;
      const hasBranchTypes = branchBusinessTypes().length > 0;
      $category.prop('disabled', !hasBranchTypes || (needsBusinessChoice && !businessKey));
      if (!$category.find('option[value="' + previous + '"]:visible').length) $category.val('');
    }

    function syncBranchContext(clearCategory) {
      rebuildBusinessTypeSelector();
      if (clearCategory) categoryFilter.val('');
      syncCategoryOptions();
    }

    function lineFigures(item) {
      const qty = cleanNum(item.quantity_received);
      const buyPrice = cleanNum(item.buying_price_per_unit);
      const conv = cleanNum(item.units_per_receiving_pack) || 1;
      const pieces = qty * conv;
      const discAmt = cleanNum(item.discount_amount);
      const discType = item.discount_type || 'fixed';

      let gross = item.buying_price_mode === 'unit' ? pieces * buyPrice : qty * buyPrice;
      let disc = discType === 'percent' ? (discAmt / 100) * gross : discAmt;
      const netCost = Math.max(0, gross - disc);
      const sellPerPiece = resolveSellPerPiece(item);
      const expectedSelling = pieces * sellPerPiece;
      return { qty, pieces, gross, disc, netCost, expectedSelling, profit: expectedSelling - netCost };
    }

    function getBuyCostPerPiece(item) {
      const qty = cleanNum(item.quantity_received);
      const buyPrice = cleanNum(item.buying_price_per_unit);
      const conv = cleanNum(item.units_per_receiving_pack) || 1;
      const pieces = qty * conv;

      if (pieces > 0) {
        return lineFigures(item).netCost / pieces;
      }

      return item.buying_price_mode === 'unit' ? buyPrice : buyPrice / conv;
    }

    function getMinRetailPrice(item, pkg) {
      const qpu = Math.max(1, cleanNum(pkg?.quantity_per_unit) || cleanNum(item.units_per_receiving_pack) || 1);
      return getBuyCostPerPiece(item) * qpu;
    }

    function validateItemRetailPrices(item) {
      const buyPrice = cleanNum(item.buying_price_per_unit);
      if (buyPrice <= 0) return null;

      const packagings = item.packagings || [];

      if (packagings.length <= 1) {
        const pkg = packagings[0] || { quantity_per_unit: item.units_per_receiving_pack || 1, name: item.unit || 'Unit' };
        const retail = resolvePackagingRetailPrice(item, pkg);
        const minRetail = getMinRetailPrice(item, pkg);

        if (retail > 0 && retail + 0.009 < minRetail) {
          return `<strong>${item.name}</strong>: sell price (${Math.round(retail).toLocaleString()} TZS) cannot be lower than buying cost (${Math.round(minRetail).toLocaleString()} TZS).`;
        }

        return null;
      }

      for (const pkg of packagings) {
        const retail = resolvePackagingRetailPrice(item, pkg);
        const minRetail = getMinRetailPrice(item, pkg);

        if (retail > 0 && retail + 0.009 < minRetail) {
          return `<strong>${item.name}</strong> (${pkg.name}): sell price (${Math.round(retail).toLocaleString()} TZS) cannot be lower than buying cost (${Math.round(minRetail).toLocaleString()} TZS).`;
        }
      }

      return null;
    }

    function updateSummaries() {
      let totalPackages = 0, totalPieces = 0, grossPurchase = 0, totalDiscount = 0;
      let totalCost = 0, totalSelling = 0, totalProfit = 0;

      receiptItems.forEach(item => {
        const qty = cleanNum(item.quantity_received);
        if (qty <= 0) return;

        const fig = lineFigures(item);
        totalPackages += qty;
        totalPieces += fig.pieces;
        grossPurchase += fig.gross;
        totalDiscount += fig.disc;
        totalCost += fig.netCost;
        totalSelling += fig.expectedSelling;
        totalProfit += fig.profit;
      });

      const margin = totalSelling > 0 ? (totalProfit / totalSelling) * 100 : 0;
      const roi = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;
      const activeCount = receiptItems.filter(item => cleanNum(item.quantity_received) > 0).length;

      $('#summ_packages').text(totalPackages.toLocaleString());
      $('#summ_units').text(Math.round(totalPieces).toLocaleString());
      $('#summ_gross').text(Math.round(grossPurchase).toLocaleString());
      $('#summ_discount').text(Math.round(totalDiscount).toLocaleString());
      $('#summ_cost').text(Math.round(totalCost).toLocaleString());
      $('#summ_unit_cost').text(totalPieces > 0 ? Math.round(totalCost / totalPieces).toLocaleString() : '0');
      $('#summ_selling').text(Math.round(totalSelling).toLocaleString());
      $('#summ_profit').text(Math.round(totalProfit).toLocaleString());
      $('#summ_margin').text(margin.toFixed(1) + '%');
      $('#summ_roi').text(roi.toFixed(1) + '%');
      $('#items_badge').text(activeCount + ' Items');
      $('#summary_items_badge').text(activeCount + ' Items');
      submitBtn.prop('disabled', activeCount === 0);
    }

    function addItemFromCatalog(item, defaultQty) {
      if (receiptItems.some(ri => String(ri.id) === String(item.id))) return false;
      receiptItems.push({
        id: item.id,
        name: item.name,
        unit: item.unit,
        units_per_receiving_pack: item.units_per_receiving_pack || 1,
        current_stock: item.current_stock,
        buying_price_per_unit: item.cost_price,
        last_known_buy: item.cost_price,
        selling_price_per_unit: item.selling_price,
        quantity_received: defaultQty,
        buying_price_mode: 'pkg',
        discount_type: 'fixed',
        discount_amount: 0,
        packagings: item.packagings || [],
        selling_prices: {},
      });
      return true;
    }

    function retailPriceSummary(item) {
      const packagings = item.packagings || [];
      if (packagings.length <= 1) {
        const pkg = packagings[0] || { quantity_per_unit: 1, selling_price: 0 };
        const price = resolvePackagingRetailPrice(item, pkg);
        return price > 0 ? Math.round(price).toLocaleString() + ' TZS' : 'Set price';
      }
      const configured = packagings.filter(pkg => resolvePackagingRetailPrice(item, pkg) > 0).length;
      return configured > 0
        ? `${configured}/${packagings.length} units set`
        : `Configure ${packagings.length} units`;
    }

    let activeRetailIdx = null;

    function openRetailPriceModal(idx) {
      activeRetailIdx = idx;
      const item = receiptItems[idx];
      if (!item) return;

      $('#rpmItemName').text(item.name);
      const packagings = item.packagings || [];
      let html = '';

      if (packagings.length <= 1) {
        const pkg = packagings[0] || { quantity_per_unit: item.units_per_receiving_pack || 1 };
        const minRetail = getMinRetailPrice(item, pkg);
        const price = resolvePackagingRetailPrice(item, pkg) || '';
        html = `<div class="form-group mb-0">
          <label class="small font-weight-bold text-uppercase text-muted">Selling price (TZS)</label>
          <input type="number" class="form-control rpm-price-input rpm-single-price" value="${price}" min="0" step="0.01" placeholder="0" autofocus>
          <small class="text-muted d-block mt-1">Minimum allowed: ${Math.round(minRetail).toLocaleString()} TZS (based on buying cost)</small>
        </div>`;
      } else {
        let rows = '';
        packagings.forEach(pkg => {
          const storedPrice = resolvePackagingRetailPrice(item, pkg) || '';
          const minRetail = getMinRetailPrice(item, pkg);
          rows += `<tr>
            <td><strong>${pkg.name}</strong></td>
            <td class="text-center">${pkg.quantity_per_unit}</td>
            <td>
              <input type="number" class="form-control form-control-sm rpm-price-input rpm-pkg-price"
                     data-pkg-id="${pkg.id}" value="${storedPrice}" min="0" step="0.01" placeholder="0">
              <small class="text-muted d-block">Min: ${Math.round(minRetail).toLocaleString()} TZS</small>
          </td>
          </tr>`;
        });
        html = `<table class="table table-sm table-bordered mb-0">
          <thead class="thead-light">
            <tr><th>Sale unit</th><th class="text-center" width="70">Pcs</th><th width="160">Price (TZS)</th></tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>`;
      }

      $('#retailPriceModalBody').html(html);
      $('#retailPriceModal').modal('show');
      setTimeout(() => $('#retailPriceModalBody input:first').focus().select(), 300);
    }

    function renderTable() {
      if (receiptItems.length === 0) {
        itemsTableBody.html(emptyTableHtml());
        updateSummaries();
        return;
      }

      itemsTableBody.empty();

      receiptItems.forEach((item, index) => {
        const remains = formatStockQty(item.current_stock);
        const conv = item.units_per_receiving_pack || 1;
        const receiveMeta = conv > 1
          ? `Receive as: ${item.unit} · ${conv} pcs/${item.unit}`
          : `Receive as: ${item.unit}`;
        const modeLabel = item.buying_price_mode === 'unit' ? 'Per piece' : `Per ${item.unit}`;
        const priceSummary = retailPriceSummary(item);
        const sellCell = `<button type="button" class="btn btn-sm btn-block open-retail-modal py-1" data-index="${index}" title="Set sell price">
               <i class="fa fa-tag"></i> ${priceSummary}
             </button>`;

        itemsTableBody.append(`
          <tr class="receiving-item-row" data-item-idx="${index}">
            <td class="px-3 product-cell">
              <div class="font-weight-bold text-dark">${item.name}</div>
              <div class="mt-1 mb-1">
                <span class="receiving-remains" title="Current stock in pieces">Remains: ${remains}</span>
              </div>
              <div class="item-meta-line">${receiveMeta}</div>
          </td>
            <td class="text-center">
              <input type="number" class="form-control form-control-sm item-pkg" data-index="${index}"
                     value="${item.quantity_received || ''}" min="0" placeholder="0">
          </td>
            <td class="col-money">
              <input type="number" class="form-control form-control-sm item-buy-price receiving-price-field"
                     data-index="${index}" value="${item.buying_price_per_unit || ''}" min="0" step="0.01" placeholder="0">
              <div class="item-meta-line">
                <span class="toggle-price-mode" data-index="${index}">${modeLabel}</span>
                · Last ${Math.round(item.last_known_buy || 0).toLocaleString()}
              </div>
          </td>
            <td class="col-money">${sellCell}</td>
            <td class="col-money">
              <div class="input-group input-group-sm">
                <input type="number" class="form-control form-control-sm item-discount-amount"
                       data-index="${index}" value="${item.discount_amount || 0}" min="0" step="0.01">
                <div class="input-group-append">
                  <select class="custom-select custom-select-sm item-discount-type" data-index="${index}" style="max-width:58px;">
                    <option value="fixed" ${item.discount_type === 'fixed' ? 'selected' : ''}>TZS</option>
                    <option value="percent" ${item.discount_type === 'percent' ? 'selected' : ''}>%</option>
            </select>
                </div>
              </div>
          </td>
            <td class="text-center">
              <button type="button" class="btn btn-link p-0 text-danger remove-item" data-index="${index}" title="Remove">
                <i class="fa fa-times-circle fa-lg"></i>
              </button>
          </td>
          </tr>
        `);
      });

      updateSummaries();
    }

    $('#business-type-selector').on('change', function () {
      categoryFilter.val('');
      syncCategoryOptions();
    });

    $('#branch_id').on('change', function () {
      if (receiptItems.length > 0) {
        Swal.fire({
          title: 'Branch Changed',
          text: 'Business types and categories were updated for this branch. Clear the receipt list if needed.',
          icon: 'info',
          confirmButtonColor: '#940000',
        });
      }
      syncBranchContext(true);
    });

    syncBranchContext(false);

    itemSearchInput.on('input', function () {
      const query = $(this).val().toLowerCase().trim();
      if (query.length < 2) { searchDropdown.hide(); return; }
      const branchId = getSelectedBranchId();
      const businessKey = hasMultipleBusinessTypes ? ($('#business-type-selector').val() || '') : '';
      const filtered = allFlatItems.filter(function (item) {
        if (!item.name.toLowerCase().includes(query)) return false;
        if (branchId && String(item.branchId) !== branchId) return false;
        if (hasMultipleBusinessTypes && businessKey) {
          const catOption = categoryFilter.find('option[value="' + item.categoryId + '"]');
          if (catOption.length && String(catOption.attr('data-business-type')) !== String(businessKey)) return false;
        }
        return true;
      });
      if (!filtered.length) {
        searchDropdown.html('<div class="p-3 text-muted small text-center">No items found.</div>').show();
        return;
      }
      let html = '';
      filtered.slice(0, 12).forEach(item => {
        html += `<div class="search-item-option" data-id="${item.id}">
          <strong>${item.name}</strong>
          <small class="text-muted d-block">Stock: ${formatStockQty(item.current_stock)} · ${item.unit}</small>
        </div>`;
      });
      searchDropdown.html(html).show();
    });

    $(document).on('click', function (e) {
      if (!$(e.target).closest('#item-search-input, #search-results-dropdown').length) searchDropdown.hide();
    });

    searchDropdown.on('click', '.search-item-option', function () {
      const item = allFlatItems.find(i => String(i.id) === String($(this).data('id')));
      if (!item) return;
      itemSearchInput.val('');
      searchDropdown.hide();
      const existingIdx = receiptItems.findIndex(i => String(i.id) === String(item.id));
      if (existingIdx !== -1) {
        receiptItems[existingIdx].quantity_received = cleanNum(receiptItems[existingIdx].quantity_received) + 1;
        renderTable();
        itemsTableBody.find(`tr.receiving-item-row[data-item-idx="${existingIdx}"]`).addClass('row-flash');
        Toast.fire({ icon: 'info', title: `${item.name} quantity increased` });
      } else {
        addItemFromCatalog(item, 1);
        renderTable();
        Toast.fire({ icon: 'success', title: `${item.name} added to receipt` });
      }
    });

    categoryFilter.on('change', function () {
      const category = $(this).val();
      if (!category) return;
      if (!$('#supplier_id').val()) {
        Swal.fire('Supplier Required', 'Please select a supplier first.', 'warning');
        $(this).val('');
        return;
      }
      const categoryItems = itemsByCategory[category] || [];
      if (!categoryItems.length) {
        Swal.fire('Empty Category', 'No items found in this category.', 'warning');
        return;
      }
      let loaded = 0;
      categoryItems.forEach(item => { if (addItemFromCatalog(item, 0)) loaded++; });
      renderTable();
      if (loaded > 0) {
        Toast.fire({ icon: 'success', title: `${loaded} item(s) loaded into receipt` });
      }
    });

    $(document).on('click', '.open-retail-modal', function () {
      openRetailPriceModal($(this).data('index'));
    });

    $('#retailPriceModalSave').on('click', function () {
      const item = receiptItems[activeRetailIdx];
      if (!item) return;

      const packagings = item.packagings || [];
      const draft = {
        selling_price_per_unit: item.selling_price_per_unit,
        selling_prices: { ...item.selling_prices },
      };

      if (packagings.length <= 1) {
        draft.selling_price_per_unit = cleanNum($('.rpm-single-price').val());
      } else {
        $('.rpm-pkg-price').each(function () {
          draft.selling_prices[$(this).data('pkg-id')] = cleanNum($(this).val());
        });
      }

      const checkItem = { ...item, ...draft };
      const priceError = validateItemRetailPrices(checkItem);

      if (priceError) {
        Swal.fire({ title: 'Invalid Sell Price', html: priceError, icon: 'error', confirmButtonColor: '#940000' });
        return;
      }

      if (packagings.length <= 1) {
        item.selling_price_per_unit = draft.selling_price_per_unit;
        if (packagings[0]) {
          item.selling_prices[packagings[0].id] = draft.selling_price_per_unit;
        }
      } else {
        item.selling_prices = draft.selling_prices;
      }

      $('#retailPriceModal').modal('hide');
      renderTable();
      Toast.fire({ icon: 'success', title: 'Sell price saved' });
    });

    $(document).on('click', '.toggle-price-mode', function () {
      const idx = $(this).data('index');
      receiptItems[idx].buying_price_mode = receiptItems[idx].buying_price_mode === 'unit' ? 'pkg' : 'unit';
      renderTable();
    });

    $(document).on('input change', '.item-pkg, .item-buy-price, .item-discount-type, .item-discount-amount', function () {
      let idx = $(this).data('item-idx');
      if (idx === undefined) idx = $(this).data('index');
      const item = receiptItems[idx];
      if (!item) return;

      if ($(this).hasClass('item-pkg')) item.quantity_received = cleanNum($(this).val());
      if ($(this).hasClass('item-buy-price')) item.buying_price_per_unit = cleanNum($(this).val());
      if ($(this).hasClass('item-discount-type')) item.discount_type = $(this).val();
      if ($(this).hasClass('item-discount-amount')) item.discount_amount = cleanNum($(this).val());
      updateSummaries();
    });

    $(document).on('click', '.remove-item', function () {
      receiptItems.splice($(this).data('index'), 1);
      renderTable();
    });

    $('#stockReceiptForm').on('submit', function (e) {
      e.preventDefault();
      if (!$('#supplier_id').val()) {
        Swal.fire('Missing Data', 'Please select a Supplier/Distributor first.', 'warning');
        return;
      }

      const activeEntries = receiptItems.filter(item => cleanNum(item.quantity_received) > 0);
      if (!activeEntries.length) {
        Swal.fire('Empty Receipt', 'Please enter a quantity for at least one item before posting.', 'warning');
        return;
      }

      let validationError = null;
      activeEntries.forEach(item => {
        if (validationError) return;
        if (cleanNum(item.buying_price_per_unit) <= 0) {
          validationError = `<strong>${item.name}</strong> is missing a <strong>Buying Price</strong>.`;
        } else if ((item.packagings || []).length <= 1) {
          const pkg = item.packagings[0] || { quantity_per_unit: 1, selling_price: 0 };
          if (resolvePackagingRetailPrice(item, pkg) <= 0) {
            validationError = `<strong>${item.name}</strong> is missing a <strong>Sell Price</strong>.`;
          }
        } else if ((item.packagings || []).length > 1) {
          const missing = item.packagings.find(pkg => resolvePackagingRetailPrice(item, pkg) <= 0);
          if (missing) validationError = `<strong>${item.name}</strong> needs a price for <strong>${missing.name}</strong>.`;
        }

        if (!validationError) {
          validationError = validateItemRetailPrices(item);
        }
      });

      if (validationError) {
        Swal.fire({ title: 'Validation Failed', html: validationError, icon: 'error', confirmButtonColor: '#940000' });
        return;
      }

      const form = this;
      Swal.fire({
        title: 'Confirm Stock Reception',
        text: `Post this receipt with ${activeEntries.length} item(s) to inventory now?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#940000',
        confirmButtonText: 'Yes, Post Receipt!',
        cancelButtonText: 'Review More'
      }).then(result => {
        if (!result.isConfirmed) return;
        $(form).find('.appended-hidden-input').remove();
        activeEntries.forEach((item, index) => {
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][id]" value="${item.id}">`);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][qty]" value="${item.quantity_received}">`);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][cost]" value="${item.buying_price_per_unit}">`);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][cost_mode]" value="${item.buying_price_mode || 'pkg'}">`);
          const primaryPkg = item.packagings[0];
          const primarySell = primaryPkg
            ? resolvePackagingRetailPrice(item, primaryPkg)
            : cleanNum(item.selling_price_per_unit);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][selling]" value="${primarySell}">`);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][discount_type]" value="${item.discount_type || ''}">`);
          $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][discount_value]" value="${item.discount_amount}">`);
          (item.packagings || []).forEach(pkg => {
            const val = resolvePackagingRetailPrice(item, pkg);
            $(form).append(`<input type="hidden" class="appended-hidden-input" name="items[${index}][selling_prices][${pkg.id}]" value="${val}">`);
          });
        });
        form.submit();
      });
    });
});
</script>
@endsection
