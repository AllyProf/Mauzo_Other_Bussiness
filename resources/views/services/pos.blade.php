@extends('layouts.app')

@section('title', 'Service POS')

@section('styles')
<style>
  .service-pos-page .app-title { margin-bottom: 10px; padding-bottom: 10px; }
  .service-pos-page .app-title h1 { font-size: 1.35rem; margin-bottom: 2px; }
  .service-pos-page .app-title p { margin-bottom: 0; font-size: 0.85rem; }
  .service-pos-page .app-breadcrumb { display: none; }
  .pos-container { display: flex; gap: 16px; height: calc(100vh - 220px); min-height: 420px; max-height: 900px; }
  .pos-left { flex: 7; display: flex; flex-direction: column; overflow: hidden; background: #fff; padding: 12px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); min-height: 0; }
  .pos-right { flex: 3; display: flex; flex-direction: column; background: #fff; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; min-height: 0; }
  .category-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; flex-shrink: 0; }
  .category-pill { cursor: pointer; padding: 5px 14px; border-radius: 5px; background: #6c757d; color: #fff; font-size: 12px; border: none; font-weight: bold; white-space: nowrap; }
  .category-pill.active { background: #940000; }
  .business-type-pills { display: flex; gap: 8px; overflow-x: auto; flex-shrink: 0; }
  .business-type-pill { cursor: pointer; padding: 6px 14px; border-radius: 20px; background: #fff; border: 2px solid #dee2e6; font-size: 12px; font-weight: 600; white-space: nowrap; }
  .business-type-pill.active { background: #343a40; color: #fff; border-color: #343a40; }
  .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; overflow-y: auto; flex: 1; align-content: start; min-height: 0; padding-right: 4px; }
  .pos-category-section { grid-column: 1 / -1; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; padding: 8px 2px 2px; border-bottom: 1px solid #e9ecef; margin-top: 4px; }
  .pos-category-section:first-child { margin-top: 0; }
  .item-card { border: 2px solid #17a2b8; border-radius: 8px; padding: 8px; cursor: pointer; background: #fff; height: auto; align-self: start; }
  .item-card:hover { border-color: #138496; box-shadow: 0 4px 8px rgba(23,162,184,.15); }
  .item-icon { text-align: center; font-size: 22px; color: #17a2b8; margin-bottom: 4px; }
  .item-title { font-weight: bold; font-size: 11px; line-height: 1.25; min-height: 0; max-height: 2.5em; overflow: hidden; }
  .item-price { font-weight: bold; color: #940000; font-size: 12px; }
  .add-btn { margin-top: 4px; text-align: center; font-size: 11px; color: #17a2b8; border-top: 1px solid #e9ecef; padding-top: 4px; }
  .search-bar { margin-bottom: 8px; position: relative; flex-shrink: 0; }
  .search-bar input { width: 100%; padding: 8px 8px 8px 36px; border: 1px solid #ced4da; border-radius: 5px; font-size: 13px; }
  .search-bar i { position: absolute; left: 12px; top: 10px; color: #adb5bd; }
  .cart-header { padding: 12px 15px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #17a2b8; font-size: 15px; flex-shrink: 0; }
  .cart-body { flex: 1; overflow-y: auto; padding: 12px; background: #f8f9fa; min-height: 0; }
  .cart-empty { text-align: center; color: #adb5bd; margin-top: 30px; }
  .cart-empty i { font-size: 40px; }
  .cart-item { background: #fff; border-radius: 5px; padding: 8px 10px; margin-bottom: 8px; display: flex; align-items: center; border: 1px solid #e9ecef; }
  .cart-item-details { flex: 1; }
  .cart-footer { padding: 12px 15px; border-top: 1px solid #e9ecef; flex-shrink: 0; }
  .payable-amount { display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; margin-bottom: 10px; }
  .place-order-btn { width: 100%; background: #17a2b8; color: #fff; font-weight: bold; padding: 10px; border: none; border-radius: 5px; }
  .place-order-btn:disabled { background: #ced4da; }
  .qty-selector { display: flex; align-items: center; justify-content: center; }
  .service-card .item-meta { font-size: 10px; color: #6c757d; }
  .pos-header-badge { background: #17a2b8; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; }
  @media (max-width: 991px) {
    .pos-container { flex-direction: column; height: auto; max-height: none; }
    .pos-left, .pos-right { min-height: 320px; }
  }
</style>
@endsection

@section('content')
<div class="service-pos-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-desktop"></i> Service POS <span class="pos-header-badge">No stock</span></h1>
    <p>Sell printing, scanning, salon, and other services — quantity = units (pages, hours, jobs)</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('services.categories') }}">Services</a></li>
    <li class="breadcrumb-item">Sales (POS)</li>
  </ul>
</div>

<form id="servicePosForm" action="{{ route('service-pos.store') }}" method="POST">
  @csrf
  <input type="hidden" name="sale_date" value="{{ date('Y-m-d') }}">

  <div class="pos-container">
    <div class="pos-left">
      @if($multiBusiness ?? false)
      <div class="business-type-pills mb-2" id="businessTypePills">
        <button type="button" class="business-type-pill active" data-type="all"><i class="fa fa-th"></i> All</button>
        @foreach($businessTypes as $bt)
        <button type="button" class="business-type-pill" data-type="{{ $bt['key'] }}"><i class="fa {{ $bt['icon'] }}"></i> {{ $bt['label'] }}</button>
        @endforeach
      </div>
      @endif

      <div class="category-pills" id="categoryPills">
        <button type="button" class="category-pill active" data-cat="all">All</button>
        @foreach($categories as $cat)
        <button type="button" class="category-pill" data-cat="{{ $cat->id }}" data-type="{{ $cat->source_service_type_key }}">{{ $cat->name }}</button>
        @endforeach
      </div>

      <div class="search-bar">
        <i class="fa fa-search"></i>
        <input type="text" id="serviceSearch" placeholder="Search services...">
      </div>

      <div class="items-grid" id="servicesGrid"></div>
    </div>

    <div class="pos-right">
      <div class="cart-header"><i class="fa fa-shopping-cart"></i> Service cart</div>
      <div class="cart-body" id="cartBody">
        <div class="cart-empty" id="cartEmpty">
          <i class="fa fa-briefcase"></i>
          <h5>Cart is empty</h5>
          <p class="small">Tap a service to add</p>
        </div>
      </div>
      <div class="cart-footer">
        <div class="payable-amount"><span>Total</span><span id="cartTotal">TZS 0</span></div>
        <button type="submit" class="place-order-btn" id="placeOrderBtn" disabled>Place service order</button>
      </div>
    </div>
  </div>
</form>
</div>

<div class="modal fade" id="qtyModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="qtyModalTitle">Quantity</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body text-center">
        <p class="small text-muted mb-2" id="qtyModalUnit"></p>
        <div class="qty-selector">
          <button type="button" class="btn btn-outline-secondary" id="qtyMinus">−</button>
          <input type="number" class="form-control text-center mx-2" id="qtyInput" value="1" min="1" step="1" style="width:80px">
          <button type="button" class="btn btn-outline-secondary" id="qtyPlus">+</button>
        </div>
        <p class="mt-2 mb-0"><strong id="qtyModalPrice"></strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="qtyConfirm">Add to cart</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const servicesByCategory = @json($servicesByCategory);
  const categoryNames = @json($categories->pluck('name', 'id'));
  const allServices = [];
  Object.keys(servicesByCategory).forEach(function (catId) {
    (servicesByCategory[catId] || []).forEach(function (s) {
      s.categoryId = parseInt(catId, 10);
      allServices.push(s);
    });
  });

  let cart = [];
  let pendingService = null;
  let activeType = 'all';
  let activeCat = 'all';

  const grid = document.getElementById('servicesGrid');
  const cartBody = document.getElementById('cartBody');
  const cartEmpty = document.getElementById('cartEmpty');
  const cartTotal = document.getElementById('cartTotal');
  const placeBtn = document.getElementById('placeOrderBtn');
  const form = document.getElementById('servicePosForm');

  function money(n) { return 'TZS ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }); }

  function filteredServices() {
    return allServices.filter(function (s) {
      if (activeType !== 'all' && s.businessTypeKey !== activeType) return false;
      if (activeCat !== 'all' && s.categoryId !== parseInt(activeCat, 10)) return false;
      const q = (document.getElementById('serviceSearch').value || '').toLowerCase();
      if (q && s.name.toLowerCase().indexOf(q) === -1) return false;
      return (s.price || 0) > 0;
    });
  }

  function serviceCardHtml(s) {
    return '<div class="item-card service-card" data-id="' + s.id + '">' +
      '<div class="item-icon"><i class="fa fa-briefcase"></i></div>' +
      '<div class="item-title">' + s.name + '</div>' +
      '<div class="item-meta">' + s.unit_label + '</div>' +
      '<div class="item-price">' + money(s.price) + '</div>' +
      '<div class="add-btn"><i class="fa fa-plus"></i> Add</div></div>';
  }

  function renderGrid() {
    const list = filteredServices();
    if (!list.length) {
      grid.innerHTML = '<p class="text-muted p-3">No active services. <a href="{{ route('services.categories') }}">Configure services</a>.</p>';
      bindServiceCards();
      return;
    }

    if (activeCat === 'all') {
      const grouped = {};
      list.forEach(function (s) {
        const key = s.categoryId;
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(s);
      });
      let html = '';
      Object.keys(grouped).sort(function (a, b) {
        return String(categoryNames[a] || '').localeCompare(String(categoryNames[b] || ''));
      }).forEach(function (catId) {
        html += '<div class="pos-category-section">' + (categoryNames[catId] || 'Category') + '</div>';
        grouped[catId].forEach(function (s) { html += serviceCardHtml(s); });
      });
      grid.innerHTML = html;
    } else {
      grid.innerHTML = list.map(serviceCardHtml).join('');
    }
    bindServiceCards();
  }

  function bindServiceCards() {
    grid.querySelectorAll('.service-card').forEach(function (el) {
      el.addEventListener('click', function () {
        const id = parseInt(el.getAttribute('data-id'), 10);
        pendingService = allServices.find(function (x) { return x.id === id; });
        if (!pendingService) return;
        document.getElementById('qtyModalTitle').textContent = pendingService.name;
        document.getElementById('qtyModalUnit').textContent = pendingService.unit_label;
        document.getElementById('qtyInput').value = 1;
        document.getElementById('qtyModalPrice').textContent = money(pendingService.price);
        $('#qtyModal').modal('show');
      });
    });
  }

  function renderCart() {
    cartBody.querySelectorAll('.cart-item').forEach(function (el) { el.remove(); });
    if (!cart.length) {
      cartEmpty.style.display = 'block';
      placeBtn.disabled = true;
      cartTotal.textContent = money(0);
      return;
    }
    cartEmpty.style.display = 'none';
    let total = 0;
    cart.forEach(function (line, idx) {
      total += line.qty * line.price;
      const div = document.createElement('div');
      div.className = 'cart-item';
      div.innerHTML = '<div class="cart-item-details"><div class="cart-item-title">' + line.name + '</div>' +
        '<div class="cart-item-price">' + line.qty + ' × ' + money(line.price) + '</div></div>' +
        '<div class="cart-item-subtotal">' + money(line.qty * line.price) + '</div>' +
        '<span class="remove-item" data-idx="' + idx + '"><i class="fa fa-times"></i></span>';
      cartBody.appendChild(div);
    });
    cartBody.querySelectorAll('.remove-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        cart.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
        renderCart();
        syncFormInputs();
      });
    });
    cartTotal.textContent = money(total);
    placeBtn.disabled = false;
  }

  function syncFormInputs() {
    form.querySelectorAll('[data-cart-line]').forEach(function (el) { el.remove(); });
    cart.forEach(function (line, i) {
      ['service_id', 'qty', 'price'].forEach(function (field) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'lines[' + i + '][' + field + ']';
        inp.setAttribute('data-cart-line', '1');
        inp.value = field === 'service_id' ? line.id : (field === 'qty' ? line.qty : line.price);
        form.appendChild(inp);
      });
    });
  }

  document.getElementById('qtyConfirm').addEventListener('click', function () {
    const qty = parseInt(document.getElementById('qtyInput').value, 10) || 1;
    if (!pendingService) return;
    const existing = cart.find(function (c) { return c.id === pendingService.id; });
    if (existing) existing.qty += qty;
    else cart.push({ id: pendingService.id, name: pendingService.name, qty: qty, price: pendingService.price });
    $('#qtyModal').modal('hide');
    renderCart();
    syncFormInputs();
  });

  document.getElementById('qtyMinus').addEventListener('click', function () {
    const inp = document.getElementById('qtyInput');
    inp.value = Math.max(1, parseInt(inp.value, 10) - 1);
  });
  document.getElementById('qtyPlus').addEventListener('click', function () {
    const inp = document.getElementById('qtyInput');
    inp.value = parseInt(inp.value, 10) + 1;
  });

  document.getElementById('serviceSearch').addEventListener('input', renderGrid);

  document.querySelectorAll('#categoryPills .category-pill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#categoryPills .category-pill').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeCat = btn.getAttribute('data-cat');
      renderGrid();
    });
  });

  document.querySelectorAll('#businessTypePills .business-type-pill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#businessTypePills .business-type-pill').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      activeType = btn.getAttribute('data-type');
      renderGrid();
    });
  });

  renderGrid();
})();
</script>
@endsection
