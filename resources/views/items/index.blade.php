@extends('layouts.app')

@section('title', __('pages.items.title') . ' - SpareParts POS')

@section('styles')
<style>
  .items-page .items-toolbar {
    align-items: center;
    gap: 12px;
    margin-bottom: 0 !important;
  }
  .items-page .items-toolbar .title h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2c3e50;
  }
  .items-page .items-filter-bars {
    padding: 0 1rem 1rem;
    border-bottom: 1px solid #e9ecef;
  }
  .items-page .items-filter-row + .items-filter-row {
    margin-top: 10px;
  }
  .items-page .items-filter-row-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6c757d;
    margin-bottom: 6px;
  }
  .items-page .business-type-tabs,
  .items-page .category-tabs {
    flex: none;
    width: 100%;
    max-width: 100%;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    align-items: stretch;
    gap: 8px;
    padding-bottom: 2px;
    scrollbar-width: thin;
    scrollbar-color: #c8cdd3 transparent;
  }
  .items-page .business-type-tabs::-webkit-scrollbar,
  .items-page .category-tabs::-webkit-scrollbar {
    height: 5px;
  }
  .items-page .business-type-tabs::-webkit-scrollbar-thumb,
  .items-page .category-tabs::-webkit-scrollbar-thumb {
    background: #c8cdd3;
    border-radius: 4px;
  }
  .items-page .business-type-tab,
  .items-page .category-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    box-sizing: border-box;
    vertical-align: top;
  }

  .items-page .items-name-cell strong {
    display: block;
    line-height: 1.35;
  }
  .items-page .items-mobile-meta {
    margin-top: 4px;
    line-height: 1.45;
  }
  .items-page .items-mobile-meta .badge {
    font-size: 10px;
    font-weight: 500;
    margin: 2px 4px 2px 0;
  }
  .items-page .items-actions .btn-group {
    flex-wrap: nowrap;
  }

  @media (max-width: 991.98px) {
    .items-page .app-title {
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 18px;
    }
    .items-page .app-title h1 {
      font-size: 1.35rem;
      line-height: 1.35;
    }
    .items-page .app-title p {
      display: block !important;
      font-size: 0.88rem;
      font-style: normal;
    }
    .items-page .app-breadcrumb {
      width: 100%;
    }
    .items-page .items-toolbar {
      flex-direction: column;
      align-items: stretch !important;
    }
    .items-page .items-filter-bars {
      padding-left: 0.75rem;
      padding-right: 0.75rem;
    }
    .items-page .items-toolbar-side {
      width: 100%;
      flex-direction: column;
      align-items: stretch !important;
    }
    .items-page .items-plan-usage {
      width: 100% !important;
      margin-right: 0 !important;
      margin-bottom: 12px;
    }
    .items-page .items-add-wrap {
      width: 100%;
    }
    .items-page .items-add-wrap .btn {
      width: 100%;
    }
  }

  @media (max-width: 767.98px) {
    .items-page .app-title h1 {
      font-size: 1.2rem;
    }
    .items-page .app-title p {
      font-size: 0.82rem;
    }
    .items-page .tile {
      padding: 14px;
    }
    .items-page .alert {
      font-size: 0.88rem;
      padding: 0.65rem 0.85rem;
    }
    .items-page .items-col-hide-mobile {
      display: none !important;
    }
    .items-page .items-actions .btn {
      padding: 0.35rem 0.5rem;
    }
    .items-page .items-actions .btn-group {
      display: inline-flex;
      gap: 4px;
    }
    .items-page .items-actions form {
      display: inline-block !important;
    }
    .items-page #sampleTable {
      font-size: 13px;
    }
    .items-page #sampleTable thead th {
      font-size: 11px;
      white-space: nowrap;
    }
    .items-page .items-table-wrap {
      margin: 0 -4px;
      border: 0;
    }
    .items-page div.dataTables_wrapper div.dataTables_length,
    .items-page div.dataTables_wrapper div.dataTables_filter {
      text-align: left;
      margin-bottom: 10px;
    }
    .items-page div.dataTables_wrapper div.dataTables_length select,
    .items-page div.dataTables_wrapper div.dataTables_filter input {
      width: 100%;
      max-width: none;
      margin-left: 0;
      margin-top: 4px;
    }
    .items-page div.dataTables_wrapper div.dataTables_filter label,
    .items-page div.dataTables_wrapper div.dataTables_length label {
      width: 100%;
      text-align: left;
    }
    .items-page div.dataTables_wrapper .row:first-child > [class*="col-"] {
      flex: 0 0 100%;
      max-width: 100%;
      padding-left: 0;
      padding-right: 0;
    }
    .items-page div.dataTables_wrapper div.dataTables_info,
    .items-page div.dataTables_wrapper div.dataTables_paginate {
      text-align: center;
      margin-top: 8px;
    }
    .items-page div.dataTables_wrapper .pagination {
      flex-wrap: wrap;
      justify-content: center;
    }
  }

</style>
@endsection

@section('content')
<div class="items-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-th-list"></i> {{ __('pages.items.title') }}</h1>
    <p>{{ __('pages.items.subtitle') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">{{ __('menu.items') }}</a></li>
  </ul>
</div>

@if($multiBusiness ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>Multi-department shop:</strong> use the tabs below to filter items by business type.
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn items-toolbar">
        <div class="title mb-0">
          <h3 class="mb-0">All Items</h3>
        </div>
        <div class="d-flex align-items-center items-toolbar-side flex-shrink-0">
            @php
                $maxItems = $business->plan->max_items ?? 0;
                $currentItems = $items->count();
                $percentage = $maxItems > 0 ? min(100, ($currentItems / $maxItems) * 100) : 0;
                $progressColor = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
            @endphp
            @if($maxItems > 0)
                <div class="mr-md-4 items-plan-usage" style="width: 200px;">
                    <small>Plan Usage: <strong>{{ $currentItems }}/{{ $maxItems }}</strong> items</small>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-{{ $progressColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            @endif
            @can('add_items')
            <p class="mb-0 items-add-wrap"><a class="btn btn-primary icon-btn {{ ($maxItems > 0 && $currentItems >= $maxItems) ? 'disabled' : '' }}" href="{{ route('items.create') }}">
                <i class="fa fa-plus"></i> Add Item
            </a></p>
            @endcan
        </div>
      </div>

      @if(($multiBusiness ?? false) || ($categoryFilters ?? collect())->isNotEmpty() || ($hasUncategorizedItems ?? false))
      <div class="items-filter-bars">
        @if($multiBusiness ?? false)
        <div class="items-filter-row">
          <span class="items-filter-row-label">Department</span>
          <div class="business-type-tabs" id="businessTypeTabs">
            <button type="button" class="business-type-tab active" data-business-type="all">
              <i class="fa fa-th-large"></i> All
            </button>
            @foreach($businessTypes as $type)
            <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
              <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
            </button>
            @endforeach
          </div>
        </div>
        @endif
        @if(($categoryFilters ?? collect())->isNotEmpty() || ($hasUncategorizedItems ?? false))
        <div class="items-filter-row">
          @if($multiBusiness ?? false)
          <span class="items-filter-row-label">Category</span>
          @endif
          <div class="category-tabs" id="categoryTabs">
            <button type="button" class="category-tab active" data-category="all">All Categories</button>
            @foreach($categoryFilters as $cat)
            <button type="button" class="category-tab"
                    data-category="{{ $cat['slug'] }}"
                    data-business-type="{{ $cat['business_type_key'] }}">
              {{ $cat['name'] }}
            </button>
            @endforeach
            @if($hasUncategorizedItems ?? false)
            <button type="button" class="category-tab" data-category="uncategorized" data-business-type="other">
              Uncategorized
            </button>
            @endif
          </div>
        </div>
        @endif
      </div>
      @endif

      <div class="tile-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-responsive items-table-wrap">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.name') }}</th>
              <th class="items-col-hide-mobile">{{ __('tables.columns.sku') }}</th>
              <th class="items-col-hide-mobile">{{ __('tables.columns.category') }}</th>
              <th class="items-col-hide-mobile">{{ __('tables.columns.brand') }}</th>
              <th class="items-col-hide-mobile">{{ __('tables.columns.packaging') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $item)
                @php
                  $businessTypeKey = $item->category?->source_business_type_key ?: 'other';
                  $categorySlug = $item->category ? \Illuminate\Support\Str::slug($item->category->name) : 'uncategorized';
                @endphp
                <tr data-business-type="{{ $businessTypeKey }}" data-category="{{ $categorySlug }}">
                    <td>
                      <div class="items-name-cell">
                        <strong>{{ $item->name }}</strong>
                        <div class="d-md-none items-mobile-meta">
                          @if($item->sku)
                            <small class="text-muted d-block">SKU: {{ $item->sku }}</small>
                          @endif
                          <small class="text-muted d-block">{{ $item->category->name ?? 'N/A' }}@if($item->brand) · {{ $item->brand }}@endif</small>
                          @foreach($item->packagings->take(2) as $pkg)
                            <span class="badge badge-info">{{ $pkg->packagingType->name }}</span>
                          @endforeach
                          @if($item->packagings->count() > 2)
                            <span class="badge badge-light border">+{{ $item->packagings->count() - 2 }}</span>
                          @endif
                        </div>
                      </div>
                    </td>
                    <td class="items-col-hide-mobile">{{ $item->sku }}</td>
                    <td class="items-col-hide-mobile">{{ $item->category->name ?? 'N/A' }}</td>
                    <td class="items-col-hide-mobile">{{ $item->brand }}</td>
                    <td class="items-col-hide-mobile">
                        @foreach($item->packagings as $pkg)
                            <span class="badge badge-info">{{ $pkg->packagingType->name }} ({{ $pkg->quantity_per_unit }} per unit)</span>
                        @endforeach
                    </td>
                    <td class="items-actions">
                        <div class="btn-group">
                            <a href="{{ route('items.show', $item->id) }}" class="btn btn-sm btn-primary" title="View Details"><i class="fa fa-eye"></i><span class="d-none d-md-inline"> View</span></a>
                            @can('edit_items')
                            <a href="{{ route('items.edit', $item->id) }}" class="btn btn-sm btn-info" title="Edit Item"><i class="fa fa-edit"></i></a>
                            @endcan
                            @can('delete_items')
                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" class="item-delete-form" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                  class="btn btn-sm btn-danger item-delete-btn"
                                  title="Delete Item"
                                  data-confirm-title="{{ __('pages.items.delete_confirm_title') }}"
                                  data-confirm-text="{{ __('pages.items.delete_confirm_text', ['name' => $item->name]) }}">
                                  <i class="fa fa-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            @if($items->isEmpty())
                <tr class="empty-row">
                    <td colspan="6" class="text-center">No items registered yet.</td>
                </tr>
            @endif
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">
    $(function () {
        const hasMultipleBusinessTypes = @json($multiBusiness ?? false);
        const hasCategoryTabs = $('#categoryTabs').length > 0;
        let activeBusinessType = 'all';
        let activeCategory = 'all';
        const isMobile = window.matchMedia('(max-width: 767.98px)').matches;

        const table = $('#sampleTable').DataTable({
            order: [[0, 'asc']],
            pageLength: isMobile ? 10 : 25,
            lengthMenu: isMobile ? [[10, 25, 50], [10, 25, 50]] : [[10, 25, 50, 100], [10, 25, 50, 100]],
        });

        $('#sampleTable').on('click', '.item-delete-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = this;
            const form = btn.closest('form');
            if (!form) {
                return;
            }
            const title = btn.getAttribute('data-confirm-title') || 'Delete item?';
            const text = btn.getAttribute('data-confirm-text') || '';
            if (typeof window.confirmAction === 'function') {
                window.confirmAction(e, title, text);
                return;
            }
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#940000',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, proceed!',
                    cancelButtonText: 'No, cancel!',
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });

        function refreshCategoryTabVisibility() {
            if (!hasCategoryTabs) {
                return;
            }

            $('#categoryTabs .category-tab').each(function () {
                const $tab = $(this);
                const tabCategory = String($tab.attr('data-category') || 'all');
                const tabBusinessType = String($tab.attr('data-business-type') || '');

                if (tabCategory === 'all') {
                    $tab.show();
                    return;
                }

                if (activeBusinessType === 'all' || !tabBusinessType || tabBusinessType === activeBusinessType) {
                    $tab.show();
                } else {
                    $tab.hide();
                }
            });

            const $active = $('#categoryTabs .category-tab.active:visible');
            if (!$active.length) {
                $('#categoryTabs .category-tab[data-category="all"]').addClass('active');
                activeCategory = 'all';
            }
        }

        if (hasMultipleBusinessTypes || hasCategoryTabs) {
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'sampleTable') {
                    return true;
                }

                const row = table.row(dataIndex).node();
                const $row = $(row);

                if (hasMultipleBusinessTypes && activeBusinessType !== 'all') {
                    if (String($row.attr('data-business-type')) !== String(activeBusinessType)) {
                        return false;
                    }
                }

                if (hasCategoryTabs && activeCategory !== 'all') {
                    if (String($row.attr('data-category')) !== String(activeCategory)) {
                        return false;
                    }
                }

                return true;
            });
        }

        if (hasMultipleBusinessTypes) {
            $('#businessTypeTabs .business-type-tab').on('click', function () {
                $('#businessTypeTabs .business-type-tab').removeClass('active');
                $(this).addClass('active');
                activeBusinessType = String($(this).attr('data-business-type') || 'all');
                refreshCategoryTabVisibility();
                table.draw();
            });
        }

        if (hasCategoryTabs) {
            refreshCategoryTabVisibility();

            $('#categoryTabs .category-tab').on('click', function () {
                if (!$(this).is(':visible')) {
                    return;
                }

                $('#categoryTabs .category-tab').removeClass('active');
                $(this).addClass('active');
                activeCategory = String($(this).attr('data-category') || 'all');
                table.draw();
            });
        }
    });
    </script>
@endsection
