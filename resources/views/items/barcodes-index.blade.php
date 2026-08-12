@extends('layouts.app')

@section('title', __('qr_codes.title'))

@section('styles')
<style>
  .qr-codes-page .category-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    width: 100%;
    margin-bottom: 14px;
  }
  .qr-codes-page .category-tab {
    cursor: pointer;
    padding: 5px 12px;
    border-radius: 20px;
    background: #fff;
    color: #495057;
    font-size: 11px;
    white-space: nowrap;
    border: 1px solid #dee2e6;
    font-weight: 600;
    transition: all .15s ease;
    line-height: 1.5;
    flex-shrink: 0;
  }
  .qr-codes-page .category-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .qr-codes-page .category-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .qr-codes-page .bulk-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    padding: 10px 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 14px;
  }
  .qr-codes-page .bulk-bar .count {
    font-size: 13px;
    color: #555;
    margin-right: auto;
  }
  .qr-codes-page tr.is-hidden { display: none; }
  .qr-codes-page .search-wrap { position: relative; }
  .qr-codes-page .search-wrap .fa-search {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
  }
  .qr-codes-page .search-wrap input { padding-left: 34px; }
</style>
@endsection

@section('content')
<div class="qr-codes-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-qrcode"></i> {{ __('qr_codes.title') }}</h1>
    <p>{{ __('qr_codes.subtitle') }}</p>
    @if($activeBranchName)
      <p class="mb-0"><span class="badge badge-info">{{ __('qr_codes.branch') }}: {{ $activeBranchName }}</span></p>
    @endif
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">{{ __('menu.registration') }}</li>
    <li class="breadcrumb-item active">{{ __('qr_codes.title') }}</li>
  </ul>
</div>

<div class="tile">
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row align-items-end mb-2">
    <div class="col-md-6 col-lg-5 form-group">
      <label class="font-weight-bold">{{ __('qr_codes.search') }}</label>
      <div class="search-wrap">
        <i class="fa fa-search"></i>
        <input type="search" id="qrSearch" class="form-control" placeholder="{{ __('qr_codes.search_placeholder') }}" autocomplete="off">
      </div>
    </div>
  </div>

  @if(($categoryFilters ?? collect())->isNotEmpty() || ($hasUncategorizedItems ?? false))
  <div class="category-tabs" id="categoryTabs">
    <button type="button" class="category-tab active" data-category="all">{{ __('qr_codes.all_categories') }}</button>
    @foreach($categoryFilters as $cat)
      <button type="button" class="category-tab"
              data-category="{{ $cat['slug'] }}"
              data-category-id="{{ $cat['id'] }}">
        {{ $cat['name'] }}
      </button>
    @endforeach
    @if($hasUncategorizedItems ?? false)
      <button type="button" class="category-tab" data-category="uncategorized" data-category-id="0">
        {{ __('qr_codes.uncategorized') }}
      </button>
    @endif
  </div>
  @endif

  <div class="bulk-bar">
    <span class="count" id="visibleCount">0</span>
    <button type="button" class="btn btn-success btn-sm" id="btnPrintSelected" disabled>
      <i class="fa fa-print"></i> {{ __('qr_codes.print_selected') }}
    </button>
    <button type="button" class="btn btn-primary btn-sm" id="btnPrintVisible" style="background:#940000;border-color:#940000;">
      <i class="fa fa-print"></i> {{ __('qr_codes.print_visible') }}
    </button>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintCategory">
      <i class="fa fa-folder-open"></i> {{ __('qr_codes.print_category') }}
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrintAll">
      <i class="fa fa-print"></i> {{ __('qr_codes.print_all') }}
    </button>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-bordered mb-0" id="qrItemsTable">
      <thead>
        <tr>
          <th style="width:36px;">
            <input type="checkbox" id="selectAllVisible" title="{{ __('qr_codes.select_all_visible') }}">
          </th>
          <th>{{ __('qr_codes.item') }}</th>
          <th>{{ __('qr_codes.category') }}</th>
          <th>{{ __('qr_codes.packaging') }}</th>
          <th>{{ __('qr_codes.codes') }}</th>
          <th class="text-center" style="min-width: 120px;">{{ __('qr_codes.action') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $item)
          @php
            $packagings = $item->packagings->sortBy('quantity_per_unit')->values();
            $codes = $packagings->pluck('barcode')->filter()->values();
            $categorySlug = $item->category ? \Illuminate\Support\Str::slug($item->category->name) : 'uncategorized';
            $searchText = strtolower(trim($item->name.' '.($item->sku ?? '').' '.$codes->implode(' ')));
          @endphp
          <tr class="qr-item-row"
              data-item-id="{{ $item->id }}"
              data-category="{{ $categorySlug }}"
              data-category-id="{{ $item->category_id ?? 0 }}"
              data-search="{{ $searchText }}">
            <td>
              <input type="checkbox" class="item-check" value="{{ $item->id }}">
            </td>
            <td>
              <strong>{{ $item->name }}</strong>
              @if($item->sku)
                <br><small class="text-muted">SKU: {{ $item->sku }}</small>
              @endif
            </td>
            <td>{{ $item->category->name ?? '—' }}</td>
            <td>
              @foreach($packagings as $pkg)
                <div>{{ $pkg->packagingType?->name ?? 'Unit' }}</div>
              @endforeach
            </td>
            <td>
              @forelse($codes as $code)
                <div><code class="small">{{ $code }}</code></div>
              @empty
                <span class="text-muted">—</span>
              @endforelse
            </td>
            <td class="text-center text-nowrap">
              <a href="{{ route('items.barcodes.print', ['item' => $item, 'code_type' => 'qr']) }}"
                 class="btn btn-success btn-sm" target="_blank" title="{{ __('qr_codes.print') }}">
                <i class="fa fa-print"></i>
              </a>
              <a href="{{ route('items.show', $item) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('qr_codes.view') }}">
                <i class="fa fa-eye"></i>
              </a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <p id="noResults" class="text-center text-muted py-4 d-none">{{ __('qr_codes.no_items') }}</p>
  </div>
</div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const bulkPrintUrl = @json(route('items.barcodes.print-bulk'));
  const searchInput = document.getElementById('qrSearch');
  const rows = Array.from(document.querySelectorAll('.qr-item-row'));
  const noResults = document.getElementById('noResults');
  const visibleCountEl = document.getElementById('visibleCount');
  const selectAllVisible = document.getElementById('selectAllVisible');
  const btnPrintSelected = document.getElementById('btnPrintSelected');
  const btnPrintVisible = document.getElementById('btnPrintVisible');
  const btnPrintCategory = document.getElementById('btnPrintCategory');
  const btnPrintAll = document.getElementById('btnPrintAll');
  let activeCategory = 'all';

  function visibleRows() {
    return rows.filter(function (row) { return !row.classList.contains('is-hidden'); });
  }

  function selectedIds() {
    return visibleRows()
      .map(function (row) { return row.querySelector('.item-check'); })
      .filter(function (cb) { return cb && cb.checked; })
      .map(function (cb) { return cb.value; });
  }

  function updateCounts() {
    const visible = visibleRows();
    const selected = selectedIds();
    visibleCountEl.textContent = @json(__('qr_codes.visible_count')).replace(':count', visible.length).replace(':selected', selected.length);
    btnPrintSelected.disabled = selected.length === 0;
    btnPrintVisible.disabled = visible.length === 0;
    noResults.classList.toggle('d-none', visible.length > 0);
    document.getElementById('qrItemsTable').classList.toggle('d-none', visible.length === 0);

    const checks = visible.map(function (row) { return row.querySelector('.item-check'); });
    const allChecked = checks.length > 0 && checks.every(function (cb) { return cb.checked; });
    selectAllVisible.checked = allChecked;
    selectAllVisible.indeterminate = !allChecked && selected.length > 0;
  }

  function applyFilters() {
    const term = (searchInput.value || '').trim().toLowerCase();

    rows.forEach(function (row) {
      const matchesCategory = activeCategory === 'all' || row.dataset.category === activeCategory;
      const matchesSearch = term === '' || (row.dataset.search || '').includes(term);
      row.classList.toggle('is-hidden', !(matchesCategory && matchesSearch));
    });

    updateCounts();
  }

  function openBulkPrint(params) {
    const url = new URL(bulkPrintUrl, window.location.origin);
    Object.keys(params).forEach(function (key) {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        url.searchParams.set(key, params[key]);
      }
    });
    url.searchParams.set('code_type', 'qr');
    window.open(url.toString(), '_blank');
  }

  searchInput.addEventListener('input', applyFilters);

  document.querySelectorAll('#categoryTabs .category-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('#categoryTabs .category-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      activeCategory = tab.dataset.category || 'all';
      applyFilters();
    });
  });

  document.querySelectorAll('.item-check').forEach(function (cb) {
    cb.addEventListener('change', updateCounts);
  });

  selectAllVisible.addEventListener('change', function () {
    visibleRows().forEach(function (row) {
      const cb = row.querySelector('.item-check');
      if (cb) cb.checked = selectAllVisible.checked;
    });
    updateCounts();
  });

  btnPrintSelected.addEventListener('click', function () {
    const ids = selectedIds();
    if (!ids.length) return;
    openBulkPrint({ items: ids.join(',') });
  });

  btnPrintVisible.addEventListener('click', function () {
    const ids = visibleRows().map(function (row) { return row.dataset.itemId; });
    if (!ids.length) return;
    openBulkPrint({ items: ids.join(',') });
  });

  btnPrintCategory.addEventListener('click', function () {
    if (activeCategory === 'all') {
      openBulkPrint({ all: '1' });
      return;
    }
    if (activeCategory === 'uncategorized') {
      openBulkPrint({ category_scope: 'uncategorized' });
      return;
    }
    const activeTab = document.querySelector('#categoryTabs .category-tab.active');
    const categoryId = activeTab ? activeTab.dataset.categoryId : '';
    if (categoryId) {
      openBulkPrint({ category_id: categoryId });
    }
  });

  btnPrintAll.addEventListener('click', function () {
    openBulkPrint({ all: '1' });
  });

  applyFilters();
})();
</script>
@endsection
