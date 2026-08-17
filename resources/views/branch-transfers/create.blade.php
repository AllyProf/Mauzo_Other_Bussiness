@extends('layouts.app')

@section('title', __('branch_transfers.new'))

@section('styles')
<style>
  .supply-page .qty-input { max-width: 110px; }
  .supply-page .unit-select { min-width: 120px; }
  .supply-page .filter-tabs,
  .supply-page .category-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
  .supply-page .filter-tab,
  .supply-page .category-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 12px; border: 1px solid #dee2e6; font-weight: 600;
  }
  .supply-page .filter-tab.active,
  .supply-page .category-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .supply-page .category-tab:hover:not(.active),
  .supply-page .filter-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .supply-page tr.is-hidden { display: none; }
  .supply-page tr.is-selected { background: #fff8f0; }
  .supply-page tr.status-empty { opacity: 0.6; }
  .supply-page .select-col { width: 42px; text-align: center; }
  .supply-page .selection-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px 14px; margin-top: 12px;
  }
  .supply-page .pager-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    margin-top: 12px;
  }
  .supply-page .pager-bar .pagination { margin: 0; }
  .supply-page .pager-bar .page-link { cursor: pointer; color: #940000; }
  .supply-page .pager-bar .page-item.active .page-link {
    background: #940000; border-color: #940000; color: #fff;
  }
  .supply-page .pager-bar .page-item.disabled .page-link { cursor: default; }
</style>
@endsection

@section('content')
<div class="supply-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-exchange"></i> {{ __('branch_transfers.new') }}</h1>
    <p>{{ __('branch_transfers.hint') }}</p>
  </div>
  <a href="{{ route('branch-transfers.index') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> {{ __('branch_transfers.back') }}</a>
</div>

@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('branch-transfers.create') }}" class="tile p-3 mb-3" id="branchPickForm">
  <div class="row align-items-end">
    <div class="col-md-4 form-group mb-md-0">
      <label class="font-weight-bold">{{ __('branch_transfers.from') }}</label>
      <input type="text" class="form-control" value="{{ $mainBranch->name }}" readonly>
    </div>
    <div class="col-md-4 form-group mb-md-0">
      <label class="font-weight-bold">{{ __('branch_transfers.to') }} *</label>
      <select name="to_branch_id" id="to_branch_id" class="form-control" onchange="this.form.submit()">
        @foreach($destinations as $branch)
          <option value="{{ $branch->id }}" @selected((int) $toBranch->id === (int) $branch->id)>{{ $branch->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <small class="text-muted">{{ __('branch_transfers.select_hint') }}</small>
    </div>
  </div>
</form>

<form method="POST" action="{{ route('branch-transfers.store') }}" id="supplyForm">
  @csrf
  <input type="hidden" name="to_branch_id" value="{{ $toBranch->id }}">

  <div class="tile">
    <div class="tile-title-w-btn flex-wrap">
      <h3 class="title mb-2">{{ __('branch_transfers.main_stock_title', ['branch' => $mainBranch->name]) }}</h3>
      <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
        <input type="search" id="supplySearch" class="form-control form-control-sm" placeholder="{{ __('branch_transfers.search') }}" style="width: 220px;">
      </div>
    </div>
    <div class="tile-body">
      <div class="filter-tabs" id="supplyFilters">
        <button type="button" class="filter-tab active" data-filter="available">{{ __('branch_transfers.filter_available') }}</button>
        <button type="button" class="filter-tab" data-filter="all">{{ __('branch_transfers.filter_all') }}</button>
      </div>
      @php
        $categoryFilters = collect($catalog)
            ->map(fn ($row) => [
                'name' => $row['category'] ?: __('branch_transfers.uncategorized'),
                'slug' => $row['category_slug'] ?? 'uncategorized',
            ])
            ->unique('slug')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
      @endphp
      @if($categoryFilters->isNotEmpty())
      <div class="category-tabs" id="categoryTabs">
        <button type="button" class="category-tab active" data-category="all">{{ __('branch_transfers.all_categories') }}</button>
        @foreach($categoryFilters as $cat)
          <button type="button" class="category-tab" data-category="{{ $cat['slug'] }}">{{ $cat['name'] }}</button>
        @endforeach
      </div>
      @endif

      <div class="row mb-3">
        <div class="col-md-4 form-group">
          <label class="font-weight-bold">{{ __('branch_transfers.date') }}</label>
          <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', date('Y-m-d')) }}" required>
        </div>
        <div class="col-md-8 form-group">
          <label class="font-weight-bold">{{ __('branch_transfers.notes') }}</label>
          <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="1000" placeholder="Optional">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="supplyTable">
          <thead>
            <tr>
              <th class="select-col">
                <input type="checkbox" id="selectAllVisible" title="{{ __('branch_transfers.select_all') }}">
              </th>
              <th>{{ __('branch_transfers.item') }}</th>
              <th>{{ __('branch_transfers.main_stock') }}</th>
              <th style="width: 150px;">{{ __('branch_transfers.unit') }}</th>
              <th style="width: 140px;">{{ __('branch_transfers.qty') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($catalog as $i => $row)
              @php
                $oldQty = old('items.'.$i.'.qty');
                $oldPackagingId = old('items.'.$i.'.item_packaging_id', $row['default_packaging_id'] ?? 0);
                $preselected = $oldQty !== null && (float) $oldQty > 0;
                $packagings = $row['packagings'] ?? [];
              @endphp
              <tr class="supply-row {{ $row['from_stock'] > 0 ? '' : 'status-empty' }} {{ $preselected ? 'is-selected' : '' }}"
                  data-name="{{ strtolower($row['name'].' '.($row['brand'] ?? '').' '.($row['sku'] ?? '')) }}"
                  data-category="{{ $row['category_slug'] ?? 'uncategorized' }}"
                  data-available="{{ $row['from_stock'] > 0 ? '1' : '0' }}"
                  data-can="{{ $row['can_transfer'] ? '1' : '0' }}"
                  data-stock="{{ $row['from_stock'] }}">
                <td class="select-col">
                  <input type="checkbox" class="item-select" {{ $row['can_transfer'] ? '' : 'disabled' }} {{ $preselected ? 'checked' : '' }}>
                  <input type="hidden" name="items[{{ $i }}][from_item_id]" value="{{ $row['from_item_id'] }}">
                </td>
                <td>
                  <strong>{{ $row['name'] }}</strong>
                  @if($row['brand'])<br><small class="text-muted">{{ $row['brand'] }}</small>@endif
                  @if($row['category'])<br><small class="text-muted">{{ $row['category'] }}</small>@endif
                </td>
                <td>
                  <strong>{{ $row['from_stock_display'] }}</strong>
                </td>
                <td>
                  @if($row['can_transfer'] && count($packagings))
                    <select class="form-control form-control-sm unit-select" name="items[{{ $i }}][item_packaging_id]" {{ $preselected ? '' : 'disabled' }}>
                      @foreach($packagings as $pkg)
                        <option value="{{ $pkg['id'] }}"
                                data-qpu="{{ $pkg['quantity_per_unit'] }}"
                                data-max="{{ $pkg['max_qty'] }}"
                                data-name="{{ $pkg['name'] }}"
                                @selected((string) $oldPackagingId === (string) $pkg['id'])>
                          {{ $pkg['name'] }}@if($pkg['quantity_per_unit'] > 1) ({{ $pkg['quantity_per_unit'] }} pcs)@endif
                        </option>
                      @endforeach
                    </select>
                  @else
                    <input type="hidden" name="items[{{ $i }}][item_packaging_id]" value="{{ $row['default_packaging_id'] ?? 0 }}">
                    <span class="text-muted">{{ __('branch_transfers.pcs') }}</span>
                  @endif
                </td>
                <td>
                  @if($row['can_transfer'])
                    <input type="number" class="form-control form-control-sm qty-input" name="items[{{ $i }}][qty]"
                           min="0" step="1"
                           value="{{ $preselected ? $oldQty : '' }}"
                           placeholder="0" {{ $preselected ? '' : 'disabled' }}>
                    <small class="text-muted qty-hint">{{ __('branch_transfers.max') }} {{ $row['max_qty'] }}</small>
                  @else
                    <input type="hidden" name="items[{{ $i }}][qty]" value="0">
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(count($catalog) === 0)
        <p class="text-muted text-center py-4 mb-0">{{ __('branch_transfers.empty_main') }}</p>
      @endif
      <p id="noVisibleRows" class="text-muted text-center py-3 d-none">{{ __('branch_transfers.empty_filter') }}</p>
      <div class="pager-bar" id="supplyPager"></div>

      <div class="selection-bar">
        <span id="selectionSummary">{{ __('branch_transfers.none_selected') }}</span>
        <div>
          <a href="{{ route('branch-transfers.index') }}" class="btn btn-secondary">{{ __('branch_transfers.back') }}</a>
          <button type="submit" class="btn btn-primary" id="supplySubmit" {{ count($catalog) ? '' : 'disabled' }}>
            <i class="fa fa-exchange"></i> {{ __('branch_transfers.submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</form>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const searchInput = document.getElementById('supplySearch');
  const rows = Array.from(document.querySelectorAll('.supply-row'));
  const emptyEl = document.getElementById('noVisibleRows');
  const selectAll = document.getElementById('selectAllVisible');
  const summaryEl = document.getElementById('selectionSummary');
  const form = document.getElementById('supplyForm');
  const pagerEl = document.getElementById('supplyPager');
  const PAGE_SIZE = 10;
  let filter = 'available';
  let category = 'all';
  let currentPage = 1;
  const showingTpl = @json(__('branch_transfers.page_showing'));
  const prevLabel = @json(__('branch_transfers.prev'));
  const nextLabel = @json(__('branch_transfers.next'));

  function selectedPackaging(row) {
    const select = row.querySelector('.unit-select');
    if (!select) {
      return { qpu: 1, max: parseFloat(row.dataset.stock || '0') || 0, name: 'pcs' };
    }
    const option = select.options[select.selectedIndex];
    return {
      qpu: Math.max(1, parseInt(option && option.dataset.qpu ? option.dataset.qpu : '1', 10) || 1),
      max: parseInt(option && option.dataset.max ? option.dataset.max : '0', 10) || 0,
      name: (option && option.dataset.name) || 'pcs'
    };
  }

  function updateQtyHint(row) {
    const hint = row.querySelector('.qty-hint');
    const qty = row.querySelector('.qty-input');
    const pkg = selectedPackaging(row);
    if (qty) {
      qty.max = pkg.max;
    }
    if (hint) {
      hint.textContent = @json(__('branch_transfers.max')) + ' ' + pkg.max + ' ' + pkg.name
        + (pkg.qpu > 1 ? ' (' + pkg.qpu + ' pcs)' : '');
    }
  }

  function setRowSelected(row, selected) {
    const checkbox = row.querySelector('.item-select');
    const qty = row.querySelector('.qty-input');
    const unit = row.querySelector('.unit-select');
    if (!checkbox || checkbox.disabled) return;
    checkbox.checked = selected;
    row.classList.toggle('is-selected', selected);
    if (qty) {
      qty.disabled = !selected;
      if (!selected) qty.value = '';
      if (selected && !qty.value) qty.focus();
    }
    if (unit) unit.disabled = !selected;
    updateQtyHint(row);
  }

  function matchingRows() {
    const term = (searchInput.value || '').trim().toLowerCase();
    return rows.filter(function (row) {
      const available = row.dataset.available === '1';
      const matchesFilter = filter === 'all' || available;
      const matchesCategory = category === 'all' || (row.dataset.category || 'uncategorized') === category;
      const matchesSearch = term === '' || (row.dataset.name || '').indexOf(term) !== -1;
      return matchesFilter && matchesCategory && matchesSearch;
    });
  }

  function renderPager(total, totalPages) {
    if (!pagerEl) return;
    if (total === 0) {
      pagerEl.innerHTML = '';
      return;
    }
    const from = ((currentPage - 1) * PAGE_SIZE) + 1;
    const to = Math.min(currentPage * PAGE_SIZE, total);
    const info = showingTpl
      .replace(':from', String(from))
      .replace(':to', String(to))
      .replace(':total', String(total));

    let buttons = '';
    buttons += '<li class="page-item' + (currentPage <= 1 ? ' disabled' : '') + '">'
      + '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">' + prevLabel + '</a></li>';

    const windowSize = 5;
    let start = Math.max(1, currentPage - Math.floor(windowSize / 2));
    let end = Math.min(totalPages, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);

    if (start > 1) {
      buttons += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
      if (start > 2) {
        buttons += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
    }
    for (let p = start; p <= end; p++) {
      buttons += '<li class="page-item' + (p === currentPage ? ' active' : '') + '">'
        + '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
    }
    if (end < totalPages) {
      if (end < totalPages - 1) {
        buttons += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
      buttons += '<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>';
    }

    buttons += '<li class="page-item' + (currentPage >= totalPages ? ' disabled' : '') + '">'
      + '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">' + nextLabel + '</a></li>';

    pagerEl.innerHTML = '<span class="text-muted small">' + info + '</span>'
      + '<nav><ul class="pagination pagination-sm">' + buttons + '</ul></nav>';
  }

  function apply(resetPage) {
    if (resetPage) currentPage = 1;
    const matched = matchingRows();
    const totalPages = Math.max(1, Math.ceil(matched.length / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const start = (currentPage - 1) * PAGE_SIZE;
    const pageRows = matched.slice(start, start + PAGE_SIZE);
    const pageSet = new Set(pageRows);

    rows.forEach(function (row) {
      row.classList.toggle('is-hidden', !pageSet.has(row));
    });
    if (emptyEl) emptyEl.classList.toggle('d-none', matched.length > 0 || rows.length === 0);
    renderPager(matched.length, totalPages);
    syncSelectAll();
  }

  function visibleRows() {
    return rows.filter(function (row) { return !row.classList.contains('is-hidden'); });
  }

  function syncSelectAll() {
    if (!selectAll) return;
    const selectable = visibleRows().filter(function (row) {
      const box = row.querySelector('.item-select');
      return box && !box.disabled;
    });
    const checked = selectable.filter(function (row) { return row.querySelector('.item-select').checked; });
    selectAll.checked = selectable.length > 0 && checked.length === selectable.length;
    selectAll.indeterminate = checked.length > 0 && checked.length < selectable.length;
  }

  function updateSummary() {
    let items = 0;
    let pieces = 0;
    rows.forEach(function (row) {
      const box = row.querySelector('.item-select');
      const qty = row.querySelector('.qty-input');
      if (box && box.checked) {
        items++;
        const pkg = selectedPackaging(row);
        pieces += (parseFloat(qty && qty.value ? qty.value : 0) || 0) * pkg.qpu;
      }
    });
    if (!summaryEl) return;
    if (items === 0) {
      summaryEl.textContent = @json(__('branch_transfers.none_selected'));
    } else {
      summaryEl.textContent = items + ' ' + @json(__('branch_transfers.selected_items')) + ' · ' + pieces + ' ' + @json(__('branch_transfers.pieces'));
    }
  }

  document.querySelectorAll('#supplyFilters .filter-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('#supplyFilters .filter-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      filter = tab.dataset.filter || 'available';
      apply(true);
    });
  });

  document.querySelectorAll('#categoryTabs .category-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('#categoryTabs .category-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      category = tab.dataset.category || 'all';
      apply(true);
    });
  });

  rows.forEach(function (row) {
    const checkbox = row.querySelector('.item-select');
    const qty = row.querySelector('.qty-input');
    const unit = row.querySelector('.unit-select');
    if (checkbox) {
      checkbox.addEventListener('change', function () {
        setRowSelected(row, checkbox.checked);
        syncSelectAll();
        updateSummary();
      });
    }
    if (qty) {
      qty.addEventListener('input', function () {
        const hasQty = parseFloat(qty.value || '0') > 0;
        if (hasQty && checkbox && !checkbox.checked) {
          setRowSelected(row, true);
        }
        updateSummary();
        syncSelectAll();
      });
    }
    if (unit) {
      unit.addEventListener('change', function () {
        updateQtyHint(row);
        updateSummary();
      });
    }
    updateQtyHint(row);
  });

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      visibleRows().forEach(function (row) {
        setRowSelected(row, selectAll.checked);
      });
      updateSummary();
      syncSelectAll();
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1') {
        return;
      }
      e.preventDefault();

      rows.forEach(function (row) {
        const qty = row.querySelector('.qty-input');
        const unit = row.querySelector('.unit-select');
        if (qty && qty.disabled) qty.disabled = false;
        if (unit && unit.disabled) unit.disabled = false;
        if (qty && !qty.value) qty.value = '0';
      });

      let items = 0;
      let pieces = 0;
      rows.forEach(function (row) {
        const box = row.querySelector('.item-select');
        const qty = row.querySelector('.qty-input');
        if (box && box.checked && qty && parseFloat(qty.value || '0') > 0) {
          items++;
          const pkg = selectedPackaging(row);
          pieces += (parseFloat(qty.value) || 0) * pkg.qpu;
        }
      });

      if (items === 0) {
        rows.forEach(function (row) {
          const box = row.querySelector('.item-select');
          const qty = row.querySelector('.qty-input');
          const unit = row.querySelector('.unit-select');
          if (qty && (!box || !box.checked)) qty.disabled = true;
          if (unit && (!box || !box.checked)) unit.disabled = true;
        });
        alert(@json(__('branch_transfers.no_qty')));
        return;
      }

      const text = @json(__('branch_transfers.send_confirm_text'))
        .replace(':items', String(items))
        .replace(':pieces', String(pieces))
        .replace(':branch', @json($toBranch->name));

      Swal.fire({
        title: @json(__('branch_transfers.send_confirm_title')),
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#940000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: @json(__('branch_transfers.send_confirm_yes')),
        cancelButtonText: @json(__('branch_transfers.send_confirm_no'))
      }).then(function (result) {
        if (!result.isConfirmed) {
          rows.forEach(function (row) {
            const box = row.querySelector('.item-select');
            const qty = row.querySelector('.qty-input');
            const unit = row.querySelector('.unit-select');
            if (qty && (!box || !box.checked)) qty.disabled = true;
            if (unit && (!box || !box.checked)) unit.disabled = true;
          });
          return;
        }
        form.dataset.confirmed = '1';
        const button = document.getElementById('supplySubmit');
        if (typeof setSubmitButtonLoading === 'function') {
          setSubmitButtonLoading(form, button);
        }
        if (typeof window.appShowPageLoader === 'function') {
          window.appShowPageLoader();
        }
        form.submit();
      });
    });
  }

  if (pagerEl) {
    pagerEl.addEventListener('click', function (e) {
      const link = e.target.closest('a[data-page]');
      if (!link || link.parentElement.classList.contains('disabled')) return;
      e.preventDefault();
      const page = parseInt(link.getAttribute('data-page'), 10);
      if (!page || page === currentPage) return;
      currentPage = page;
      apply();
    });
  }

  searchInput.addEventListener('input', function () { apply(true); });
  apply();
  updateSummary();
})();
</script>
@endsection
