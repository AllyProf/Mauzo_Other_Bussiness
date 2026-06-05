@extends('layouts.app')

@section('title', __('categories.title'))

@section('styles')
<style>
  .business-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
  }
  .business-type-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 10px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: all 0.15s ease;
    min-height: 90px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .business-type-card:hover {
    border-color: #940000;
    background: #fffbfb;
  }
  .business-type-card.imported {
    border-color: #28a745;
    background: #f6fff8;
  }
  .business-type-card.imported.active,
  .business-type-card.selected {
    border-color: #940000;
    background: #940000;
    color: #fff;
  }
  .business-type-card.selected:not(.active) {
    background: #fff5f5;
    color: inherit;
  }
  .business-type-card.selected .small,
  .business-type-card.selected .imported-badge {
    color: rgba(255,255,255,0.85) !important;
  }
  .business-type-card i {
    font-size: 22px;
    margin-bottom: 6px;
  }
  .business-type-card .label-text {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.3;
  }
  .custom-template-box {
    border: 1px dashed #ced4da;
    border-radius: 8px;
    padding: 15px;
    background: #f8f9fa;
  }
  .category-type-tabs .nav-link {
    font-weight: 600;
    color: #495057;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    padding: 10px 16px;
  }
  .category-type-tabs .nav-link.active {
    color: #940000;
    border-bottom-color: #940000;
    background: transparent;
  }
  .category-type-tabs .nav-link:hover {
    color: #940000;
  }
</style>
@endsection

@section('content')
@php
  $importedKeys = collect($importedTypes)->pluck('key')->all();
  $typesUsed = $businessTypesUsed ?? $business->categoryBusinessTypesUsed();
  $typesLimit = $business->maxBusinessTypesAllowed();
  $typesRemaining = $typesLimit === null ? null : max(0, $typesLimit - $typesUsed);
  $otherCount = $categoryCountsByType['other'] ?? 0;
  $defaultCategoryTab = count($importedTypes) === 1 ? ($importedTypes[0]['key'] ?? 'all') : 'all';
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-list"></i> {{ __('categories.title') }}</h1>
    <p>{{ __('categories.subtitle') }}</p>
  </div>
</div>

@if(count($importedTypes) > 0)
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-check-circle"></i>
  <strong>
    @if($branchFilterId ?? null)
      {{ __('categories.imported_types_for', ['branch' => $activeBranchName]) }}
    @else
      {{ __('categories.imported_types') }}:
    @endif
  </strong>
  @foreach($importedTypesMeta ?? $importedTypes as $imported)
    <span class="badge badge-light ml-1">
      {{ $imported['label'] ?? __('categories.business') }}@if(str_starts_with($imported['key'] ?? '', 'custom:')) ({{ __('categories.custom') }})@endif
      @if(!empty($imported['branch_names']))
        <span class="text-muted">→ {{ implode(', ', $imported['branch_names']) }}</span>
      @endif
    </span>
  @endforeach
  <span class="text-muted ml-2">· {{ __('categories.categories_loaded', ['count' => $categories->count()]) }}</span>
</div>
@endif

<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-credit-card"></i>
  @php $limitLabel = $typesLimit === null ? __('categories.unlimited') : (string) $typesLimit; @endphp
  {{ __('categories.plan_allowance', ['plan' => $business->plan->name ?? __('categories.default_plan'), 'limit' => $limitLabel]) }}
  @if($typesLimit !== null)
    <span class="ml-1">{{ __('categories.used', ['count' => $typesUsed]) }}@if($typesRemaining !== null) · {{ __('categories.remaining', ['count' => $typesRemaining]) }}@endif</span>
  @endif
</div>

@can('add_items')
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">{{ __('categories.choose_type') }}</h3>
      <p class="text-muted small mb-3">{{ __('categories.choose_type_hint') }}</p>

      <form id="templateForm" action="{{ route('categories.import-templates') }}" method="POST">
        @csrf
        <div id="templateTypesHidden"></div>

        @include('registration.categories.partials.branch-select', ['fieldId' => 'templateBranchSelect'])

        @if(($writableBranches ?? collect())->isNotEmpty())
        <div class="business-type-grid mb-3">
          @foreach($businessTemplates as $key => $template)
            @php $isImported = in_array($key, $importedKeys, true); @endphp
            <div class="business-type-card {{ $isImported ? 'imported' : '' }}" data-type="{{ $key }}" data-label="{{ $template['label'] }}" data-categories="{{ implode(', ', $template['categories']) }}" data-imported="{{ $isImported ? '1' : '0' }}">
              <i class="fa {{ $template['icon'] ?? 'fa-store' }}"></i>
              <div class="label-text">{{ $template['label'] }}</div>
              <div class="small text-muted">{{ __('categories.categories_count', ['count' => count($template['categories'])]) }}</div>
              @if($isImported)
                <div class="small imported-badge text-success"><i class="fa fa-check"></i> {{ __('categories.imported') }}</div>
              @endif
            </div>
          @endforeach
          @foreach($importedTypes as $imported)
            @if(str_starts_with($imported['key'] ?? '', 'custom:'))
            <div class="business-type-card imported" style="cursor: default;">
              <i class="fa fa-pencil"></i>
              <div class="label-text">{{ $imported['label'] }}</div>
              <div class="small imported-badge text-success"><i class="fa fa-check"></i> {{ __('categories.custom_imported') }}</div>
            </div>
            @endif
          @endforeach
        </div>

        <button type="button" class="btn btn-info" id="btnImportTemplate" disabled>
          <i class="fa fa-magic"></i> {{ __('categories.import_selected') }}
        </button>
        @endif
      </form>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">{{ __('categories.custom_type') }}</h3>
      <p class="text-muted small mb-3">{{ __('categories.custom_type_hint') }}</p>

      @php $customImported = collect($importedTypes)->filter(fn ($t) => str_starts_with($t['key'] ?? '', 'custom:')); @endphp
      @if($customImported->isNotEmpty())
      <div class="alert alert-info py-2 mb-3">
        <i class="fa fa-store"></i> {{ __('categories.imported_custom_types') }}
        @foreach($customImported as $imported)
          <strong class="ml-1">{{ $imported['label'] }}</strong>@if(!$loop->last),@endif
        @endforeach
      </div>
      @endif

      <form id="customTemplateForm" action="{{ route('categories.import-templates') }}" method="POST">
        @csrf
        <input type="hidden" name="template_type" value="custom">
        @include('registration.categories.partials.branch-select', ['fieldId' => 'customBranchSelect'])
        <div class="custom-template-box">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group mb-md-0">
                <label class="control-label font-weight-bold">{{ __('categories.business_name') }}</label>
                <input class="form-control" type="text" name="custom_business_name" value="{{ old('custom_business_name') }}" placeholder="{{ __('categories.business_name_placeholder') }}" required>
              </div>
            </div>
            <div class="col-md-8">
              <div class="form-group mb-md-0">
                <label class="control-label font-weight-bold">{{ __('categories.categories_label') }}</label>
                <textarea class="form-control" name="custom_categories" rows="3" placeholder="{{ __('categories.categories_placeholder') }}" required></textarea>
                <small class="text-muted">{{ __('categories.categories_hint') }}</small>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-primary" id="btnImportCustom">
              <i class="fa fa-plus-circle"></i> {{ __('categories.import_custom') }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endcan

<div class="row">
  @can('add_items')
  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title">{{ __('categories.add_new') }}</h3>
      @if(count($importedTypes) > 0)
      <form action="{{ route('categories.store') }}" method="POST">
        @csrf
        @include('registration.categories.partials.branch-select', ['fieldId' => 'addCategoryBranchSelect'])
        <div class="form-group">
          <label class="control-label">{{ __('categories.business_type') }} <span class="text-danger">*</span></label>
          <select class="form-control" name="source_business_type_key" id="addCategoryTypeKey" required>
            @foreach($importedTypes as $imported)
              <option value="{{ $imported['key'] }}">{{ $imported['label'] }}</option>
            @endforeach
          </select>
          <small class="text-muted">{{ __('categories.business_type_hint') }}</small>
        </div>
        <div class="form-group">
          <label class="control-label">{{ __('categories.category_name') }}</label>
          <input class="form-control" type="text" name="name" placeholder="{{ __('categories.category_name_placeholder') }}" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> {{ __('categories.add_category') }}</button>
      </form>
      @else
      <div class="alert alert-warning mb-0">
        <i class="fa fa-lock"></i> {{ __('categories.import_first') }}
        <p class="small mb-0 mt-2">{!! __('categories.import_first_hint_html', ['choose' => __('categories.choose_type'), 'custom' => __('categories.custom_type')]) !!}</p>
      </div>
      @endif
    </div>
  </div>
  @endcan

  <div class="{{ Auth::user()->can('add_items') ? 'col-md-8' : 'col-md-12' }}">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h3 class="tile-title mb-0">{{ __('categories.your_categories') }}</h3>
          <p class="text-muted small mb-0 mt-1">{{ __('categories.tabs_hint') }}</p>
        </div>
        @can('delete_items')
          @if($categories->isNotEmpty())
          <form id="clearAllCategoriesForm" action="{{ route('categories.clear-all') }}" method="POST">
            @csrf @method('DELETE')
            <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearAllCategories">
              <i class="fa fa-eraser"></i> {{ __('categories.clear_all') }}
            </button>
          </form>
          @endif
        @endcan
      </div>

      @if(count($importedTypes) > 0 || $otherCount > 0)
      <ul class="nav nav-tabs category-type-tabs border-bottom mb-3" id="categoryTypeTabs">
        @if(count($importedTypes) > 1)
        <li class="nav-item">
          <a class="nav-link category-type-tab {{ $defaultCategoryTab === 'all' ? 'active' : '' }}" href="#" data-type="all">
            {{ __('categories.all') }} <span class="badge badge-secondary ml-1">{{ $categories->count() }}</span>
          </a>
        </li>
        @endif
        @foreach($importedTypes as $imported)
          @php $typeCount = $categoryCountsByType[$imported['key']] ?? 0; @endphp
          <li class="nav-item">
            <a class="nav-link category-type-tab {{ (count($importedTypes) === 1 || $defaultCategoryTab === $imported['key']) ? 'active' : '' }}" href="#" data-type="{{ $imported['key'] }}">
              {{ $imported['label'] }}
              @if(str_starts_with($imported['key'] ?? '', 'custom:'))
                <span class="badge badge-info ml-1">{{ __('categories.custom') }}</span>
              @endif
              <span class="badge badge-secondary ml-1">{{ $typeCount }}</span>
            </a>
          </li>
        @endforeach
        @if($otherCount > 0)
        <li class="nav-item">
          <a class="nav-link category-type-tab" href="#" data-type="other">
            {{ __('categories.other') }} <span class="badge badge-secondary ml-1">{{ $otherCount }}</span>
          </a>
        </li>
        @endif
      </ul>
      @endif

      <div class="tile-body px-0 pt-0">
        <div id="categoryTabEmpty" class="text-center text-muted py-4" style="display:none;">
          <i class="fa fa-folder-open-o fa-2x mb-2"></i>
          <p class="mb-0">{{ __('categories.no_categories_type') }}</p>
        </div>
        <table class="table table-hover table-bordered mb-0" id="categoriesTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.name') }}</th>
              @if($viewingAllBranches ?? false)
              <th>{{ __('tables.columns.branch') }}</th>
              @endif
              <th>{{ __('categories.items_count') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($categories as $category)
                @php $rowType = $category->source_business_type_key ?: 'other'; @endphp
                <tr data-business-type="{{ $rowType }}">
                    <td>
                        @can('edit_items')
                        <form action="{{ route('categories.update', $category->id) }}" method="POST" class="form-inline">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $category->name }}" class="form-control form-control-sm mr-2" required>
                            <button type="submit" class="btn btn-sm btn-link text-success"><i class="fa fa-save"></i> {{ __('categories.save') }}</button>
                        </form>
                        @else
                        {{ $category->name }}
                        @endcan
                    </td>
                    @if($viewingAllBranches ?? false)
                    <td><span class="badge badge-light border">{{ $category->branch?->name ?? '—' }}</span></td>
                    @endif
                    <td><span class="badge badge-info">{{ $category->items_count ?? 0 }} {{ __('categories.items') }}</span></td>
                    <td>
                        @can('delete_items')
                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST" class="delete-cat-form" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="button" class="btn btn-sm btn-danger btn-delete-cat" data-name="{{ $category->name }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    const catI18n = @json(__('categories.swal'));
    const importedKeys = @json($importedKeys);
    const typesLimit = @json($typesLimit);
    const typesUsed = @json($typesUsed);
    const canPickBranch = @json($canPickBranch ?? false);
    let selectedTypes = [];
    let activeCategoryTab = @json($defaultCategoryTab);

    function filterCategoriesByTab(type) {
        activeCategoryTab = type;
        let visibleCount = 0;

        $('#categoriesTable tbody tr').each(function() {
            const rowType = $(this).attr('data-business-type') || 'other';
            const show = type === 'all' || rowType === type;
            $(this).toggle(show);
            if (show) {
                visibleCount++;
            }
        });

        $('#categoryTabEmpty').toggle(visibleCount === 0);

        if ($('#addCategoryTypeKey').length && type !== 'all' && type !== 'other') {
            $('#addCategoryTypeKey').val(type);
        }
    }

    $('.category-type-tab').on('click', function(e) {
        e.preventDefault();
        $('.category-type-tab').removeClass('active');
        $(this).addClass('active');
        filterCategoriesByTab($(this).attr('data-type'));
    });

    if ($('#categoryTypeTabs').length) {
        filterCategoriesByTab(activeCategoryTab);
    } else if ($('#categoriesTable tbody tr').length) {
        $('#categoryTabEmpty').hide();
    }

    function selectedBranchLabel(selectId) {
        const $select = $(selectId);
        if (!$select.length || !$select.val()) {
            return '';
        }

        return $select.find('option:selected').text().trim();
    }

    function ensureBranchSelected(selectId) {
        if (!canPickBranch) {
            return true;
        }

        if ($(selectId).val()) {
            return true;
        }

        Swal.fire(catI18n.select_branch, catI18n.select_branch_text, 'warning');
        return false;
    }

    function syncTemplateHiddenInputs() {
        const container = $('#templateTypesHidden');
        container.empty();
        selectedTypes.forEach(function(type, index) {
            container.append('<input type="hidden" name="template_types[' + index + ']" value="' + type + '">');
        });
        $('#btnImportTemplate').prop('disabled', selectedTypes.length === 0);
    }

    function countNewTypeSlots(types) {
        return types.filter(function(type) {
            return importedKeys.indexOf(type) === -1;
        }).length;
    }

    $('.business-type-card[data-type]').on('click', function() {
        const type = $(this).data('type');
        const index = selectedTypes.indexOf(type);

        if (index >= 0) {
            selectedTypes.splice(index, 1);
            $(this).removeClass('selected');
        } else {
            selectedTypes.push(type);
            $(this).addClass('selected');
        }

        syncTemplateHiddenInputs();
    });

    $('#btnImportTemplate').on('click', function() {
        if (!ensureBranchSelected('#templateBranchSelect')) {
            return;
        }

        if (selectedTypes.length === 0) {
            Swal.fire(catI18n.select_types, catI18n.select_types_text, 'warning');
            return;
        }

        const newSlotsNeeded = countNewTypeSlots(selectedTypes);

        if (typesLimit !== null && newSlotsNeeded > (typesLimit - typesUsed)) {
            Swal.fire({
                icon: 'warning',
                title: catI18n.plan_limit,
                html: catI18n.plan_limit_html
                    .replace(':limit', typesLimit)
                    .replace(':used', typesUsed)
                    .replace(':needed', newSlotsNeeded),
                confirmButtonColor: '#940000'
            });
            return;
        }

        const labels = selectedTypes.map(function(type) {
            return $('.business-type-card[data-type="' + type + '"]').data('label');
        });
        const previews = selectedTypes.map(function(type) {
            const label = $('.business-type-card[data-type="' + type + '"]').data('label');
            const cats = $('.business-type-card[data-type="' + type + '"]').data('categories');
            return '<div style="margin-bottom:8px;"><strong>' + label + '</strong><br><span style="font-size:12px;">' + cats + '</span></div>';
        }).join('');

        const branchLabel = selectedBranchLabel('#templateBranchSelect');
        const branchHtml = branchLabel
            ? '<p class="mb-2"><strong>' + catI18n.branch_label + '</strong> ' + branchLabel + '</p>'
            : '';

        Swal.fire({
            title: catI18n.import_templates,
            html: branchHtml + '<div style="text-align:left;max-height:220px;overflow-y:auto;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + previews + '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-magic"></i> ' + catI18n.yes_import,
            cancelButtonText: catI18n.cancel,
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnImportTemplate');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + catI18n.importing);
                document.getElementById('templateForm').submit();
            }
        });
    });

    $('#btnImportCustom').on('click', function() {
        if (!ensureBranchSelected('#customBranchSelect')) {
            return;
        }

        const form = document.getElementById('customTemplateForm');
        const businessName = form.querySelector('[name="custom_business_name"]').value.trim();
        const categoriesRaw = form.querySelector('[name="custom_categories"]').value.trim();

        if (!businessName || !categoriesRaw) {
            Swal.fire(catI18n.missing_info, catI18n.missing_info_text, 'warning');
            return;
        }

        const preview = categoriesRaw
            .split(/[\r\n,]+/)
            .map(function(item) { return item.trim(); })
            .filter(function(item) { return item !== ''; })
            .join(', ');

        const branchLabel = selectedBranchLabel('#customBranchSelect');
        const branchHtml = branchLabel
            ? '<p class="mb-2"><strong>' + catI18n.branch_label + '</strong> ' + branchLabel + '</p>'
            : '';

        Swal.fire({
            title: catI18n.import_custom_title,
            html: branchHtml + '<strong>' + businessName + '</strong><br><br>' + catI18n.categories_to_add + '<br><br><div style="text-align:left;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + preview + '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-plus-circle"></i> ' + catI18n.yes_import,
            cancelButtonText: catI18n.cancel,
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnImportCustom');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + catI18n.importing);
                form.submit();
            }
        });
    });

    $(document).on('click', '.btn-delete-cat', function() {
        const btn = $(this);
        const name = btn.data('name');
        const form = btn.closest('form');

        Swal.fire({
            title: catI18n.delete_title.replace(':name', name),
            html: catI18n.delete_html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-trash"></i> ' + catI18n.yes_delete,
            cancelButtonText: catI18n.cancel,
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    $('#btnClearAllCategories').on('click', function() {
        Swal.fire({
            title: catI18n.clear_all_title,
            html: catI18n.clear_all_html.replace(':count', '{{ $categories->count() }}'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-eraser"></i> ' + catI18n.yes_clear,
            cancelButtonText: catI18n.cancel,
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnClearAllCategories');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + catI18n.clearing);
                document.getElementById('clearAllCategoriesForm').submit();
            }
        });
    });
});
</script>
@endsection
