@extends('layouts.app')

@section('title', __('pages.items.title') . ' - SpareParts POS')

@section('styles')
<style>
  .items-page .business-type-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    flex-wrap: nowrap;
    flex: 1;
    min-width: 0;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .items-page .business-type-tabs::-webkit-scrollbar { display: none; }
  .items-page .business-type-tab {
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
  .items-page .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .items-page .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .items-page .business-type-tab i { margin-right: 5px; }

  .items-page .category-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    width: 100%;
    margin-top: 10px;
  }
  .items-page .category-tab {
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
  .items-page .category-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .items-page .category-tab:hover:not(.active) { border-color: #940000; color: #940000; }

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
    .items-page .items-toolbar-tabs {
      width: 100%;
      margin-bottom: 12px;
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

  @media (max-width: 575.98px) {
    .items-page .business-type-tab {
      padding: 5px 10px;
      font-size: 10px;
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
      <div class="tile-title-w-btn flex-wrap items-toolbar">
        <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0 items-toolbar-tabs" style="flex: 1; min-width: 0;">
          @if($multiBusiness ?? false)
          <div class="business-type-tabs mr-md-3" id="businessTypeTabs">
            <button type="button" class="business-type-tab active" data-business-type="all">
              <i class="fa fa-th-large"></i> All
            </button>
            @foreach($businessTypes as $type)
            <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
              <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
            </button>
            @endforeach
          </div>
          @else
          <h3 class="title mb-0">All Items</h3>
          @endif
          @if(($categoryFilters ?? collect())->isNotEmpty() || ($hasUncategorizedItems ?? false))
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
          @endif
        </div>
        <div class="d-flex align-items-center items-toolbar-side">
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
                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete Item"><i class="fa fa-trash"></i></button>
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
