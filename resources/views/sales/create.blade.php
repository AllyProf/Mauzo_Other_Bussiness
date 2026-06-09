@extends('layouts.app')

@section('title', 'Point of Sale')

@section('styles')
<style>
    .pos-container { display: flex; height: calc(100vh - 120px); gap: 20px; }
    .pos-left { flex: 7; display: flex; flex-direction: column; overflow: hidden; background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .pos-right { flex: 3; display: flex; flex-direction: column; background: #fff; padding: 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; }

    .search-bar { margin-bottom: 15px; position: relative; }
    .search-bar input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #ced4da; border-radius: 5px; font-size: 14px;}
    .search-bar i { position: absolute; left: 15px; top: 12px; color: #adb5bd; }

    .category-pills { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 10px; }
    .category-pill { cursor: pointer; padding: 5px 15px; border-radius: 5px; background: #6c757d; color: #fff; font-size: 12px; white-space: nowrap; border: none; font-weight: bold;}
    .category-pill.active { background: #940000; }
    .category-pill:hover { background: #5a6268; }
    .category-pill.active:hover { background: #7a0000; }

    .business-type-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; flex-wrap: nowrap; }
    .business-type-pill {
        cursor: pointer; padding: 6px 14px; border-radius: 20px; background: #fff; color: #495057;
        font-size: 12px; white-space: nowrap; border: 2px solid #dee2e6; font-weight: 600;
    }
    .business-type-pill.active { background: #343a40; color: #fff; border-color: #343a40; }
    .business-type-pill:hover { border-color: #940000; color: #940000; }
    .business-type-pill.active:hover { color: #fff; border-color: #343a40; }
    .business-type-pill i { margin-right: 5px; }

    .view-toggles { display: flex; gap: 10px; margin-bottom: 10px; }
    .view-btn { padding: 5px 10px; border: 1px solid #ced4da; background: #fff; border-radius: 4px; cursor: pointer; color: #6c757d; }
    .view-btn.active { background: #940000; color: #fff; border-color: #940000; }

    .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; overflow-y: auto; padding-right: 5px; flex: 1; align-content: start;}
    .items-list { display: flex; flex-direction: column; gap: 5px; overflow-y: auto; padding-right: 5px; flex: 1; }

    .item-card { border: 2px solid #940000; border-radius: 8px; padding: 10px; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; justify-content: space-between; background: #fff;}
    .item-card:hover { border-color: #7a0000; box-shadow: 0 4px 8px rgba(148, 0, 0, 0.12); }
    .item-icon { text-align: center; font-size: 30px; color: #940000; margin-bottom: 5px; }
    .item-title { font-weight: bold; font-size: 12px; margin-bottom: 6px; color: #333; line-height: 1.2; height: 28px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;}
    .item-meta { display: flex; flex-direction: column; gap: 2px; font-size: 11px; margin-bottom: 5px;}
    .item-stock { color: #6c757d; }
    .item-price { font-weight: bold; color: #940000; }
    .item-price.unset { color: #856404; font-weight: 600; font-size: 10px; }
    .add-btn { margin-top: 5px; color: #940000; font-weight: bold; cursor: pointer; border-top: 1px solid #e9ecef; padding-top: 5px; text-align: center; font-size: 12px;}
    .add-btn i { margin-right: 5px; }
    .add-btn:hover { color: #7a0000; }

    .item-row { display: flex; justify-content: space-between; align-items: center; border: 2px solid #940000; border-radius: 5px; padding: 10px; cursor: pointer; background: #fff; transition: all 0.2s;}
    .item-row:hover { border-color: #7a0000; background: #fffbfb; box-shadow: 0 2px 6px rgba(148, 0, 0, 0.1); }
    .item-row-title { font-weight: bold; font-size: 14px; flex: 2;}
    .item-row-stock { font-size: 12px; flex: 1;}
    .item-row-price { font-weight: bold; color: #940000; flex: 1; text-align: right;}
    .item-row-price.unset { color: #856404; font-size: 12px; font-weight: 600; }

    .cart-header { padding: 15px; border-bottom: 1px solid #e9ecef; font-size: 18px; font-weight: bold; color: #940000; }
    .cart-header i { margin-right: 10px; }
    .cart-body { flex: 1; overflow-y: auto; padding: 15px; background: #f8f9fa; }
    .cart-empty { text-align: center; color: #adb5bd; margin-top: 50px; }
    .cart-empty i { font-size: 60px; margin-bottom: 15px; color: #6c757d;}
    .cart-empty h5 { color: #6c757d; font-weight: 600;}

    .cart-item { background: #fff; border-radius: 5px; padding: 10px; margin-bottom: 10px; border: 1px solid #e9ecef; display: flex; align-items: center;}
    .cart-item-details { flex: 1; }
    .cart-item-title { font-weight: bold; font-size: 13px; color: #333;}
    .cart-item-price { font-size: 12px; color: #6c757d; }
    .cart-item-subtotal { font-weight: bold; color: #940000; font-size: 14px; text-align: right; width: 80px;}
    .remove-item { color: #dc3545; cursor: pointer; padding: 5px; margin-left: 5px;}
    .remove-item:hover { color: #a71d2a; }

    .cart-footer { padding: 15px; background: #fff; border-top: 1px solid #e9ecef; }
    .totals-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #6c757d; font-weight: 600;}
    .payable-amount { display: flex; justify-content: space-between; font-size: 20px; font-weight: bold; margin-bottom: 15px; color: #000; }
    .payable-amount span:last-child { color: #940000; }
    .place-order-btn { width: 100%; background: #940000; color: #fff; font-weight: bold; padding: 12px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; text-transform: uppercase;}
    .place-order-btn:hover { background: #7a0000; }
    .place-order-btn:disabled { background: #ced4da; cursor: not-allowed; color: #6c757d;}

    .qty-selector { display: flex; align-items: center; justify-content: center; margin: 20px 0; }
    .qty-btn { background: #343a40; color: #fff; border: none; width: 40px; height: 40px; font-size: 20px; border-radius: 5px; cursor: pointer;}
    .qty-btn:hover { background: #23272b; }
    .qty-input { width: 80px; height: 40px; text-align: center; border: 1px solid #ced4da; margin: 0 10px; border-radius: 5px; font-weight: bold; font-size: 16px;}
    .modal-header { background: #940000; color: #fff; border-bottom: none;}
    .modal-header .close { color: #fff; opacity: 1;}
    .modal-title { font-weight: bold; }
    .stock-info { text-align: center; color: #6c757d; font-size: 12px; margin-bottom: 10px;}
    .price-info { text-align: center; color: #940000; font-size: 20px; font-weight: bold; margin-bottom: 20px;}
    .modal-item-name { text-align: center; font-weight: 700; font-size: 15px; color: #333; margin-bottom: 12px; }
    .packaging-switch {
        display: flex;
        gap: 0;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid #940000;
        margin-bottom: 4px;
    }
    .packaging-switch-btn {
        flex: 1;
        padding: 10px 6px;
        border: none;
        background: #fff;
        color: #495057;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        line-height: 1.25;
    }
    .packaging-switch-btn + .packaging-switch-btn {
        border-left: 1px solid #940000;
    }
    .packaging-switch-btn:hover:not(.active) {
        background: #fff5f5;
    }
    .packaging-switch-btn.active {
        background: #940000;
        color: #fff;
    }
    .packaging-switch-btn.disabled,
    .packaging-switch-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #f8f9fa;
    }
    .packaging-switch-btn small {
        display: block;
        font-size: 10px;
        font-weight: 500;
        opacity: 0.9;
        margin-top: 2px;
    }

    /* Mobile / tablet only — desktop rules above are unchanged */
    .pos-page .pos-mobile-tabs,
    .pos-page .pos-mobile-bar { display: none; }

    @media (max-width: 991.98px) {
        .pos-page {
            padding-bottom: 0;
        }
        .pos-page.pos-products-tab-active.has-cart-items {
            padding-bottom: 76px;
        }
        .pos-page .pos-mobile-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            background: #fff;
            padding: 6px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .pos-page .pos-mobile-tab {
            flex: 1;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            color: #495057;
            border-radius: 6px;
            padding: 10px 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            line-height: 1.2;
        }
        .pos-page .pos-mobile-tab.active {
            background: #940000;
            border-color: #940000;
            color: #fff;
        }
        .pos-page .pos-mobile-tab .pos-tab-badge {
            display: inline-block;
            min-width: 20px;
            padding: 1px 6px;
            margin-left: 4px;
            border-radius: 10px;
            background: rgba(255,255,255,0.25);
            font-size: 11px;
        }
        .pos-page .pos-mobile-tab:not(.active) .pos-tab-badge {
            background: #940000;
            color: #fff;
        }
        .pos-page .pos-mobile-bar {
            display: none;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1050;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
            background: #fff;
            border-top: 2px solid #940000;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.12);
        }
        .pos-page .pos-mobile-bar.is-visible {
            display: flex;
        }
        .pos-page .pos-mobile-bar-total small {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        .pos-page .pos-mobile-bar-total strong {
            display: block;
            font-size: 17px;
            color: #940000;
            line-height: 1.2;
        }
        .pos-page .pos-mobile-bar-btn {
            background: #940000;
            border-color: #940000;
            font-weight: 700;
            white-space: nowrap;
            padding: 10px 16px;
        }
        .pos-page .pos-container {
            flex-direction: column;
            height: auto;
            min-height: 0;
            gap: 0;
        }
        .pos-page .pos-left,
        .pos-page .pos-right {
            display: none !important;
            flex: none;
            width: 100%;
            overflow: visible;
        }
        .pos-page.pos-products-tab-active .pos-left {
            display: flex !important;
            flex-direction: column;
            min-height: calc(100dvh - 200px);
        }
        .pos-page.pos-cart-tab-active .pos-right {
            display: flex !important;
            flex-direction: column;
            min-height: calc(100dvh - 160px);
        }
        .pos-page .pos-left .items-grid,
        .pos-page .pos-left .items-list {
            flex: 1;
            max-height: none;
            min-height: 240px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .pos-page .cart-body {
            flex: 1;
            max-height: none;
            min-height: 120px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .pos-page .cart-empty {
            margin-top: 24px;
        }
        .pos-page .cart-empty i {
            font-size: 42px;
            margin-bottom: 10px;
        }
        .pos-page .business-type-pills,
        .pos-page .category-pills {
            -webkit-overflow-scrolling: touch;
            flex-shrink: 0;
        }
        .pos-page .search-bar {
            flex-shrink: 0;
        }
    }

    @media (max-width: 767.98px) {
        .pos-page.pos-products-tab-active .pos-left {
            min-height: calc(100dvh - 210px);
        }
        .pos-page .pos-left > .d-flex.justify-content-between {
            flex-direction: column;
            align-items: stretch;
            flex-shrink: 0;
        }
        .pos-page .pos-left > .d-flex .category-pills {
            margin-bottom: 8px !important;
            width: 100%;
        }
        .pos-page .view-toggles {
            justify-content: flex-end;
            margin-bottom: 0;
        }
        .pos-page .items-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .pos-page .item-card {
            padding: 8px;
        }
        .pos-page .item-icon {
            font-size: 24px;
        }
        .pos-page .item-title {
            height: auto;
            min-height: 28px;
            font-size: 11px;
        }
        .pos-page .item-row {
            flex-wrap: wrap;
            gap: 6px;
        }
        .pos-page .item-row-title {
            flex: 1 1 100%;
        }
        .pos-page .item-row-stock,
        .pos-page .item-row-price {
            flex: 1 1 auto;
            text-align: left;
        }
        .pos-page .cart-header {
            font-size: 16px;
            padding: 12px 15px;
            flex-shrink: 0;
        }
        .pos-page .cart-footer {
            flex-shrink: 0;
        }
        .pos-page .payable-amount {
            font-size: 18px;
        }
        .pos-page .place-order-btn {
            font-size: 15px;
            padding: 14px;
        }
        .pos-page #posCustomerManual .col-6 {
            flex: 0 0 100%;
            max-width: 100%;
            padding-left: 15px !important;
            padding-right: 15px !important;
            margin-bottom: 8px;
        }
        .pos-page .alert {
            font-size: 0.82rem;
            padding: 8px 10px;
            margin-bottom: 8px !important;
        }
        .pos-page .alert .alert-link {
            display: inline-block;
            margin-top: 2px;
        }
        .pos-page .packaging-switch-btn {
            font-size: 11px;
            padding: 8px 4px;
        }
        .pos-page .cart-item {
            flex-wrap: wrap;
        }
        .pos-page .cart-item-subtotal {
            width: auto;
            margin-left: auto;
        }
    }
</style>
@endsection

@section('content')
<div class="pos-page pos-products-tab-active">
@if($openShift ?? false)
<div class="alert alert-success mb-3 py-2">
  <i class="fa fa-clock-o"></i> <strong>Shift #{{ $openShift->id }}</strong> open since {{ $openShift->opened_at->format('h:i A') }}
  <a href="{{ route('shifts.show', $openShift) }}" class="alert-link ml-2">View shift</a>
  <a href="{{ route('day-closing.index', ['shift' => $openShift->id]) }}" class="alert-link ml-2">End shift / handover</a>
</div>
@endif
@if($multiBusiness ?? count($businessTypes ?? []) > 1)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>Multi-department shop:</strong> pick a department to filter items, then choose a category.
</div>
@endif

<div class="pos-mobile-tabs d-lg-none" id="posMobileTabs">
  <button type="button" class="pos-mobile-tab active" data-pos-tab="products">
    <i class="fa fa-th"></i> Products
  </button>
  <button type="button" class="pos-mobile-tab" data-pos-tab="cart">
    <i class="fa fa-shopping-basket"></i> Cart
    <span class="pos-tab-badge" id="mobileCartBadge">0</span>
  </button>
</div>

<div class="pos-container">
    <!-- LEFT SIDE: ITEMS GRID -->
    <div class="pos-left">
        <div class="search-bar">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search items...">
        </div>

        @if($multiBusiness ?? count($businessTypes ?? []) > 1)
        <div class="business-type-pills mb-2" id="businessTypePills">
            <button type="button" class="business-type-pill active" data-key="all"><i class="fa fa-th-large"></i> All Departments</button>
            @foreach($businessTypes as $type)
            <button type="button" class="business-type-pill" data-key="{{ $type['key'] }}"><i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}</button>
            @endforeach
        </div>
        @endif
        
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="category-pills" id="categoryPills" style="margin-bottom: 0;">
                <button type="button" class="category-pill active" data-id="all">All Items</button>
                @foreach($categories as $cat)
                    <button type="button" class="category-pill" data-id="{{ $cat->id }}" data-business-type="{{ $cat->source_business_type_key ?: 'other' }}">{{ $cat->name }}</button>
                @endforeach
            </div>
            <div class="view-toggles">
                <button class="view-btn active" id="btnGrid" title="Grid View"><i class="fa fa-th"></i></button>
                <button class="view-btn" id="btnList" title="List View"><i class="fa fa-list"></i></button>
            </div>
        </div>

        <div class="items-grid" id="itemsContainer">
            <!-- Items rendered via JS -->
        </div>
    </div>

    <!-- RIGHT SIDE: CART -->
    <div class="pos-right">
        <div class="cart-header">
            <i class="fa fa-shopping-basket"></i> Order List
        </div>
        
        <div class="cart-body" id="cartBody">
            <div class="cart-empty" id="emptyCartMessage">
                <i class="fa fa-shopping-cart"></i>
                <h5>Empty Order</h5>
                <p>Select items from the left to start</p>
            </div>
            <div id="cartItemsList">
                <!-- Cart items rendered via JS -->
            </div>
        </div>

        <div class="cart-footer">
            <form action="{{ route('sales.store') }}" method="POST" id="sales-form">
                @csrf
                <input type="hidden" name="sale_date" value="{{ date('Y-m-d') }}">
                <div id="hiddenCartInputs"></div>

                <div class="mb-2">
                    <label style="font-size: 11px; color: #6c757d; margin-bottom: 2px;">Customer</label>
                    <select name="customer_id" id="posCustomerSelect" class="form-control form-control-sm">
                        <option value="">Walk-in / Manual entry</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                    @if($customers->isEmpty())
                    <small class="text-muted d-block mt-1">
                        <a href="{{ route('customers.create') }}" target="_blank">Register a customer</a> for quick selection.
                    </small>
                    @endif
                </div>
                <div id="posCustomerManual" class="row mb-2">
                    <div class="col-6 pr-1">
                        <label style="font-size: 11px; color: #6c757d; margin-bottom: 2px;">Name</label>
                        <input type="text" name="customer_name" id="posCustomerName" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-6 pl-1">
                        <label style="font-size: 11px; color: #6c757d; margin-bottom: 2px;">Phone</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text">+255</span></div>
                            <input type="text" id="posCustomerPhone" class="form-control" placeholder="712345678" maxlength="10">
                        </div>
                        <input type="hidden" name="customer_phone" id="posCustomerPhoneHidden">
                    </div>
                </div>

                <div class="totals-row">
                    <span>Subtotal</span>
                    <span id="subtotalLabel">TZS 0</span>
                </div>
                <div class="payable-amount">
                    <span>Grand Total</span>
                    <span id="grandTotalLabel">TZS 0</span>
                </div>

                <button type="submit" class="place-order-btn" id="placeOrderBtn" disabled>
                    <i class="fa fa-file-text-o mr-2"></i> PLACE ORDER
                </button>
            </form>
        </div>
    </div>
</div>

<div class="pos-mobile-bar d-lg-none" id="posMobileBar">
  <div class="pos-mobile-bar-total">
    <small>Grand Total</small>
    <strong id="mobileBarTotal">TZS 0</strong>
  </div>
  <button type="button" class="btn btn-primary pos-mobile-bar-btn" id="posMobileViewCart">
    View Cart (<span id="mobileBarCount">0</span>)
  </button>
</div>

<!-- Add to Order Modal -->
<div class="modal fade" id="addToCartModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-plus-circle"></i> Add to Order</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-item-name" id="modalItemName"></div>
        <div class="stock-info"><i class="fa fa-database"></i> <span id="modalStockText">0 in stock</span></div>
        <div class="form-group mb-3" id="modalPackagingGroup" style="display:none;">
            <label class="control-label text-center d-block small text-uppercase text-muted mb-2">Sell as</label>
            <div class="packaging-switch" id="modalPackagingSwitch"></div>
        </div>
        <div class="price-info" id="modalPriceDisplay">TSh <span id="modalPrice">0</span> <small id="modalPriceUnit" style="font-size:12px;color:#6c757d;">per unit</small></div>
        <div class="form-group mb-3" id="modalPriceEditGroup" style="display:none;">
            <label class="control-label text-center d-block font-weight-bold">Selling Price (TZS) <span class="text-danger">*</span></label>
            <input type="number" class="form-control text-center" id="modalPriceInput" min="1" step="1" placeholder="e.g. 10000">
            <small class="text-muted d-block text-center mt-1">No price set for this item yet. Enter the price for this sale — it will be saved for next time.</small>
        </div>

        <div class="form-group">
            <label class="control-label text-center d-block" id="modalQtyLabel">Quantity</label>
            <div class="qty-selector">
                <button type="button" class="qty-btn" id="qtyMinus"><i class="fa fa-minus"></i></button>
                <input type="number" class="qty-input" id="modalQtyInput" value="1" min="1">
                <button type="button" class="qty-btn" id="qtyPlus"><i class="fa fa-plus"></i></button>
            </div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmAddToCart"><i class="fa fa-plus"></i> Add to Order</button>
      </div>
    </div>
  </div>
</div>
</div>

@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@include('sales.partials.customer-picker-scripts')
<script>
    // Prepare data
    const itemsByCategory = @json($itemsByCategory);
    const hasMultipleBusinessTypes = @json($multiBusiness ?? count($businessTypes ?? []) > 1);
    let activeBusinessType = hasMultipleBusinessTypes ? 'all' : 'all';
    let allItems = [];
    Object.keys(itemsByCategory).forEach(catId => {
        itemsByCategory[catId].forEach(item => {
            item.categoryId = catId;
            allItems.push(item);
        });
    });

    function syncCategoryPillsVisibility() {
        if (!hasMultipleBusinessTypes) {
            return;
        }

        $('#categoryPills .category-pill').each(function () {
            const $pill = $(this);
            const pillId = String($pill.attr('data-id') || '');

            if (pillId === 'all') {
                $pill.show();
                return;
            }

            const matches = activeBusinessType === 'all'
                || String($pill.attr('data-business-type')) === String(activeBusinessType);

            $pill.toggle(matches);
        });

        if ($('#categoryPills .category-pill.active:visible').length === 0) {
            $('#categoryPills .category-pill').removeClass('active');
            $('#categoryPills .category-pill[data-id="all"]').addClass('active');
        }
    }

    function filterItems() {
        const searchTerm = $('#searchInput').val().toLowerCase();
        const activeCatId = String($('#categoryPills .category-pill.active:visible').first().attr('data-id')
            || $('#categoryPills .category-pill[data-id="all"]').attr('data-id') || 'all');

        let filtered = allItems;

        if (hasMultipleBusinessTypes && activeBusinessType !== 'all') {
            filtered = filtered.filter(item => String(item.businessTypeKey) === String(activeBusinessType));
        }

        if (activeCatId !== 'all') {
            filtered = filtered.filter(item => item.categoryId == activeCatId);
        }

        if (searchTerm) {
            filtered = filtered.filter(item =>
                item.name.toLowerCase().includes(searchTerm) ||
                item.sku.toLowerCase().includes(searchTerm)
            );
        }

        renderItems(filtered);
    }

    let cart = [];
    let currentModalItem = null;
    let currentView = 'grid';

    function formatTZS(amount) {
        return amount.toLocaleString(undefined, {minimumFractionDigits: 0});
    }

    function isMobilePos() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function setPosMobileTab(tab) {
        const $page = $('.pos-page');
        $page.toggleClass('pos-products-tab-active', tab === 'products');
        $page.toggleClass('pos-cart-tab-active', tab === 'cart');
        $('.pos-mobile-tab').removeClass('active');
        $('.pos-mobile-tab[data-pos-tab="' + tab + '"]').addClass('active');
        updateMobilePosChrome();
        if (tab === 'cart') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function updateMobilePosChrome(grandTotal) {
        const count = cart.length;
        const total = grandTotal !== undefined ? grandTotal : (cart.reduce((sum, item) => sum + item.qty * item.price, 0));
        $('#mobileCartBadge, #mobileBarCount').text(count);
        $('#mobileBarTotal').text('TZS ' + formatTZS(total));

        const $page = $('.pos-page');
        $page.toggleClass('has-cart-items', count > 0);

        if (isMobilePos() && count > 0 && $page.hasClass('pos-products-tab-active')) {
            $('#posMobileBar').addClass('is-visible');
        } else {
            $('#posMobileBar').removeClass('is-visible');
        }
    }

    function packagingLabel(item, packaging) {
        if (!packaging) return '';
        if ((item.packagings || []).length <= 1) return '';
        return ' (' + packaging.name + ')';
    }

    function formatStockQty(value) {
        const num = parseFloat(value) || 0;
        return Number.isInteger(num) ? String(num) : num.toFixed(2);
    }

    function stockPieces(item) {
        return parseFloat(item.stock_pieces ?? item.stock) || 0;
    }

    function packagingMaxQty(item, pkg) {
        const pieces = stockPieces(item);
        const qpu = Math.max(1, parseInt(pkg?.quantity_per_unit, 10) || 1);
        return Math.floor(pieces / qpu);
    }

    function defaultPackaging(item) {
        const list = item.packagings || [];
        if (!list.length) {
            return {
                id: null,
                name: 'Unit',
                quantity_per_unit: 1,
                selling_price: item.selling_price,
                max_qty: stockPieces(item),
            };
        }

        const available = list.filter(p => packagingMaxQty(item, p) > 0);
        const pieceUnit = available.find(p => parseInt(p.quantity_per_unit, 10) === 1);
        if (pieceUnit) return pieceUnit;
        if (available.length) return available[0];

        return list.find(p => p.id === item.default_packaging_id) || list[0];
    }

    function itemHasPrice(item) {
        const list = item.packagings || [];
        if (list.length) {
            return list.some(p => parseFloat(p.selling_price) > 0);
        }

        return parseFloat(item.selling_price) > 0;
    }

    function itemPriceText(item) {
        const list = item.packagings || [];
        if (!itemHasPrice(item)) {
            return null;
        }
        if (list.length <= 1) {
            return formatTZS(item.selling_price);
        }
        const prices = list.map(p => p.selling_price).filter(p => p > 0);
        if (!prices.length) {
            return null;
        }
        const min = Math.min.apply(null, prices);
        const max = Math.max.apply(null, prices);
        return min === max ? formatTZS(min) : formatTZS(min) + ' – ' + formatTZS(max);
    }

    function activeUnitPrice(item, packaging) {
        return parseFloat(packaging?.selling_price ?? item?.selling_price ?? 0) || 0;
    }

    function renderItems(items) {
        const container = $('#itemsContainer');
        container.empty();

        if(items.length === 0) {
            container.html('<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #adb5bd;"><i class="fa fa-search fa-3x mb-3"></i><br>No items found</div>');
            return;
        }

        if (currentView === 'grid') {
            container.removeClass('items-list').addClass('items-grid');
            items.forEach(item => {
                const iconHtml = item.categoryId == 'placeholder' ? '<i class="fa fa-glass"></i>' : '<i class="fa fa-cube"></i>';

                const card = `
                    <div class="item-card" onclick="openModal(${item.id})">
                        <div class="item-icon">${iconHtml}</div>
                        <div class="item-title">${item.name}</div>
                        <div class="item-meta">
                            <div class="item-stock">Stock: <b>${formatStockQty(item.stock_pieces ?? item.stock)} pcs</b></div>
                            <div class="item-price${itemHasPrice(item) ? '' : ' unset'}">${itemPriceText(item) ? 'TSh ' + itemPriceText(item) : 'Price not set'}</div>
                        </div>
                        <div class="add-btn">
                            <i class="fa fa-plus-circle"></i> Add
                        </div>
                    </div>
                `;
                container.append(card);
            });
        } else {
            container.removeClass('items-grid').addClass('items-list');
            items.forEach(item => {
                const row = `
                    <div class="item-row" onclick="openModal(${item.id})">
                        <div class="item-row-title">${item.name}</div>
                        <div class="item-row-stock">Stock: ${formatStockQty(item.stock_pieces ?? item.stock)} pcs</div>
                        <div class="item-row-price${itemHasPrice(item) ? '' : ' unset'}">${itemPriceText(item) ? 'TSh ' + itemPriceText(item) : 'Price not set'}</div>
                        <div class="add-btn" style="border:none; margin:0; padding:0;"><i class="fa fa-plus-circle fa-lg"></i></div>
                    </div>
                `;
                container.append(row);
            });
        }
    }

    // Modal Logic
    let currentModalPackaging = null;

    function refreshModalPackaging() {
        if (!currentModalItem) return;

        const list = currentModalItem.packagings || [];
        const $group = $('#modalPackagingGroup');
        const $switch = $('#modalPackagingSwitch');
        const pieces = stockPieces(currentModalItem);

        if (list.length <= 1) {
            $group.hide();
            currentModalPackaging = defaultPackaging(currentModalItem);
        } else {
            $group.show();
            $switch.empty();

            if (!currentModalPackaging || packagingMaxQty(currentModalItem, currentModalPackaging) <= 0) {
                currentModalPackaging = defaultPackaging(currentModalItem);
            }

            list.forEach(pkg => {
                const avail = packagingMaxQty(currentModalItem, pkg);
                const isActive = String(pkg.id) === String(currentModalPackaging.id);
                const disabled = avail <= 0;
                $switch.append(`
                    <button type="button" class="packaging-switch-btn${isActive ? ' active' : ''}${disabled ? ' disabled' : ''}"
                            data-id="${pkg.id}" ${disabled ? 'disabled' : ''}>
                        ${pkg.name}
                        ${parseFloat(pkg.selling_price) > 0
                            ? '<small>TSh ' + formatTZS(pkg.selling_price) + ' · ' + avail + ' avail.</small>'
                            : '<small class="text-warning">Price not set · ' + avail + ' avail.</small>'}
                    </button>
                `);
            });

            if (packagingMaxQty(currentModalItem, currentModalPackaging) <= 0) {
                currentModalPackaging = defaultPackaging(currentModalItem);
                $switch.find('.packaging-switch-btn').removeClass('active');
                $switch.find(`.packaging-switch-btn[data-id="${currentModalPackaging.id}"]`).addClass('active');
            }
        }

        const maxQty = packagingMaxQty(currentModalItem, currentModalPackaging);
        const unitName = currentModalPackaging?.name || 'unit';
        const unitPrice = activeUnitPrice(currentModalItem, currentModalPackaging);
        const needsPrice = unitPrice <= 0;

        $('#modalItemName').text(currentModalItem.name);
        $('#modalStockText').text(
            formatStockQty(pieces) + ' pcs in stock · can sell up to ' + maxQty + ' ' + unitName
        );

        if (needsPrice) {
            $('#modalPriceDisplay').hide();
            $('#modalPriceEditGroup').show();
            $('#modalPriceInput').val('');
        } else {
            $('#modalPriceDisplay').show();
            $('#modalPriceEditGroup').hide();
            $('#modalPrice').text(formatTZS(unitPrice));
            $('#modalPriceUnit').text('per ' + unitName);
        }

        $('#modalQtyLabel').text('Quantity (' + unitName + ')');
        $('#modalQtyInput').val(maxQty > 0 ? 1 : 0).attr('max', Math.max(0, maxQty));
        $('#confirmAddToCart').prop('disabled', maxQty <= 0);
    }

    window.openModal = function(itemId) {
        const item = allItems.find(i => i.id == itemId);
        if (!item || stockPieces(item) <= 0) return;

        currentModalItem = item;
        currentModalPackaging = defaultPackaging(item);
        refreshModalPackaging();
        
        $('#addToCartModal').modal('show');
    };

    $(document).on('click', '.packaging-switch-btn:not(.disabled)', function () {
        if (!currentModalItem) return;
        const list = currentModalItem.packagings || [];
        const selectedId = $(this).data('id');
        currentModalPackaging = list.find(p => String(p.id) === String(selectedId)) || defaultPackaging(currentModalItem);
        refreshModalPackaging();
    });

    $('#qtyMinus').click(function() {
        let val = parseInt($('#modalQtyInput').val()) || 1;
        if (val > 1) $('#modalQtyInput').val(val - 1);
    });

    $('#qtyPlus').click(function() {
        let val = parseInt($('#modalQtyInput').val()) || 1;
        let max = parseInt($('#modalQtyInput').attr('max'));
        if (val < max) $('#modalQtyInput').val(val + 1);
    });

    $('#modalQtyInput').on('change input', function() {
        let val = parseInt($(this).val()) || 1;
        let max = parseInt($(this).attr('max'));
        if (val > max) $(this).val(max);
        if (val < 1) $(this).val(1);
    });

    $('#confirmAddToCart').click(function() {
        if (!currentModalItem) return;
        const qty = parseInt($('#modalQtyInput').val()) || 1;
        const packaging = currentModalPackaging || defaultPackaging(currentModalItem);
        const maxQty = packagingMaxQty(currentModalItem, packaging);
        if (maxQty <= 0) return;

        let price = activeUnitPrice(currentModalItem, packaging);
        if ($('#modalPriceEditGroup').is(':visible')) {
            price = parseFloat($('#modalPriceInput').val()) || 0;
            if (price <= 0) {
                alert('Please enter a selling price for this item.');
                $('#modalPriceInput').focus();
                return;
            }
        }

        const cartKey = currentModalItem.id + ':' + (packaging.id || 'default');
        
        const existingItem = cart.find(i => i.cartKey === cartKey);
        if (existingItem) {
            if (existingItem.qty + qty <= maxQty) {
                existingItem.qty += qty;
            } else {
                existingItem.qty = maxQty;
            }
            existingItem.price = price;
        } else {
            cart.push({
                cartKey: cartKey,
                id: currentModalItem.id,
                item_packaging_id: packaging.id,
                name: currentModalItem.name + packagingLabel(currentModalItem, packaging),
                packaging_name: packaging.name,
                price: price,
                qty: qty,
                maxQty: maxQty,
            });
        }
        
        $('#addToCartModal').modal('hide');
        renderCart();
        if (isMobilePos()) {
            $('#posMobileBar').addClass('is-visible');
        }
    });

    // Cart Logic
    function renderCart() {
        const list = $('#cartItemsList');
        const hiddenInputs = $('#hiddenCartInputs');
        list.empty();
        hiddenInputs.empty();

        if (cart.length === 0) {
            $('#emptyCartMessage').show();
            $('#placeOrderBtn').prop('disabled', true);
            updateTotals(0);
            updateMobilePosChrome(0);
            return;
        }

        $('#emptyCartMessage').hide();
        $('#placeOrderBtn').prop('disabled', false);

        let grandTotal = 0;

        cart.forEach((item, index) => {
            const subtotal = item.qty * item.price;
            grandTotal += subtotal;

            const html = `
                <div class="cart-item">
                    <div class="cart-item-details">
                        <div class="cart-item-title">${item.name}</div>
                        <div class="cart-item-price">TZS ${formatTZS(item.price)} × ${item.qty}</div>
                    </div>
                    <div class="cart-item-subtotal">TZS ${formatTZS(subtotal)}</div>
                    <div class="remove-item" onclick="removeFromCart(${index})" title="Remove"><i class="fa fa-trash"></i></div>
                </div>
            `;
            list.append(html);

            // Add hidden inputs for form submission
            hiddenInputs.append(`
                <input type="hidden" name="items[${index}][id]" value="${item.id}">
                <input type="hidden" name="items[${index}][item_packaging_id]" value="${item.item_packaging_id || ''}">
                <input type="hidden" name="items[${index}][qty]" value="${item.qty}">
                <input type="hidden" name="items[${index}][price]" value="${item.price}">
            `);
        });

        updateTotals(grandTotal);
        updateMobilePosChrome(grandTotal);
    }

    window.removeFromCart = function(index) {
        cart.splice(index, 1);
        renderCart();
    };

    function updateTotals(total) {
        $('#subtotalLabel').text('TZS ' + formatTZS(total));
        $('#grandTotalLabel').text('TZS ' + formatTZS(total));
    }

    // Event Listeners
    $('#businessTypePills .business-type-pill').click(function() {
        $('#businessTypePills .business-type-pill').removeClass('active');
        $(this).addClass('active');
        activeBusinessType = String($(this).attr('data-key') || 'all');
        $('#categoryPills .category-pill').removeClass('active');
        $('#categoryPills .category-pill[data-id="all"]').addClass('active');
        syncCategoryPillsVisibility();
        filterItems();
    });

    $('.category-pill').click(function() {
        $('.category-pill').removeClass('active');
        $(this).addClass('active');
        filterItems();
    });

    $('#btnGrid').click(function() {
        currentView = 'grid';
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        filterItems();
    });

    $('#btnList').click(function() {
        currentView = 'list';
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        filterItems();
    });

    $('#searchInput').on('input', filterItems);

    $('#posMobileTabs .pos-mobile-tab').on('click', function () {
        setPosMobileTab(String($(this).data('pos-tab') || 'products'));
    });

    $('#posMobileViewCart').on('click', function () {
        setPosMobileTab('cart');
    });

    $(window).on('resize', function () {
        updateMobilePosChrome();
    });

    syncCategoryPillsVisibility();
    filterItems();

    $('#posCustomerSelect').select2({
        width: '100%',
        placeholder: 'Search registered customer...',
        allowClear: true,
    });

    initCustomerPicker({
        select: '#posCustomerSelect',
        nameInput: '#posCustomerName',
        phoneInput: '#posCustomerPhone',
        phoneHidden: '#posCustomerPhoneHidden',
    });

    updateMobilePosChrome(0);
</script>
@endsection
