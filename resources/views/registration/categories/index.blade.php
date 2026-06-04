@extends('layouts.app')

@section('title', 'Categories')

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
    <h1><i class="fa fa-list"></i> Categories</h1>
    <p>Manage product categories or choose a template for your business type</p>
  </div>
</div>

@if(count($importedTypes) > 0)
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-check-circle"></i>
  <strong>
    Imported business types
    @if($branchFilterId ?? null)
      for {{ $activeBranchName }}
    @endif
    :
  </strong>
  @foreach($importedTypesMeta ?? $importedTypes as $imported)
    <span class="badge badge-light ml-1">
      {{ $imported['label'] ?? 'Business' }}@if(str_starts_with($imported['key'] ?? '', 'custom:')) (Custom)@endif
      @if(!empty($imported['branch_names']))
        <span class="text-muted">→ {{ implode(', ', $imported['branch_names']) }}</span>
      @endif
    </span>
  @endforeach
  <span class="text-muted ml-2">· {{ $categories->count() }} categories loaded</span>
</div>
@endif

<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-credit-card"></i>
  Your <strong>{{ $business->plan->name ?? 'plan' }}</strong> allows
  <strong>{{ $business->businessTypesLimitLabel() }}</strong> business type(s).
  @if($typesLimit !== null)
    <span class="ml-1">Used: <strong>{{ $typesUsed }}</strong>@if($typesRemaining !== null) · Remaining: <strong>{{ $typesRemaining }}</strong>@endif</span>
  @endif
</div>

@can('add_items')
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Choose Your Business Type</h3>
      <p class="text-muted small mb-3">Pick the branch, select one or more business types below, then import their categories. You can combine types if your plan allows (e.g. Spare Parts + Grocery). Existing categories with the same name will not be duplicated.</p>

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
              <div class="small text-muted">{{ count($template['categories']) }} categories</div>
              @if($isImported)
                <div class="small imported-badge text-success"><i class="fa fa-check"></i> Imported</div>
              @endif
            </div>
          @endforeach
          @foreach($importedTypes as $imported)
            @if(str_starts_with($imported['key'] ?? '', 'custom:'))
            <div class="business-type-card imported" style="cursor: default;">
              <i class="fa fa-pencil"></i>
              <div class="label-text">{{ $imported['label'] }}</div>
              <div class="small imported-badge text-success"><i class="fa fa-check"></i> Custom · Imported</div>
            </div>
            @endif
          @endforeach
        </div>

        <button type="button" class="btn btn-info" id="btnImportTemplate" disabled>
          <i class="fa fa-magic"></i> Import Selected Template(s)
        </button>
        @endif
      </form>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Custom Business Type</h3>
      <p class="text-muted small mb-3">If your business is not listed above, write your business name and the categories you need. Each custom business name uses one business type slot on your plan.</p>

      @php $customImported = collect($importedTypes)->filter(fn ($t) => str_starts_with($t['key'] ?? '', 'custom:')); @endphp
      @if($customImported->isNotEmpty())
      <div class="alert alert-info py-2 mb-3">
        <i class="fa fa-store"></i> Imported custom business types:
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
                <label class="control-label font-weight-bold">Business Name</label>
                <input class="form-control" type="text" name="custom_business_name" value="{{ old('custom_business_name') }}" placeholder="e.g. Mobile Accessories Shop" required>
              </div>
            </div>
            <div class="col-md-8">
              <div class="form-group mb-md-0">
                <label class="control-label font-weight-bold">Categories</label>
                <textarea class="form-control" name="custom_categories" rows="3" placeholder="Enter category names, one per line or separated by commas&#10;e.g. Chargers, Phone Cases, Screen Guards, Earphones" required></textarea>
                <small class="text-muted">Separate categories with commas or put each on a new line.</small>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="button" class="btn btn-primary" id="btnImportCustom">
              <i class="fa fa-plus-circle"></i> Import Custom Categories
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
      <h3 class="tile-title">Add New Category</h3>
      @if(count($importedTypes) > 0)
      <form action="{{ route('categories.store') }}" method="POST">
        @csrf
        @include('registration.categories.partials.branch-select', ['fieldId' => 'addCategoryBranchSelect'])
        <div class="form-group">
          <label class="control-label">Business Type <span class="text-danger">*</span></label>
          <select class="form-control" name="source_business_type_key" id="addCategoryTypeKey" required>
            @foreach($importedTypes as $imported)
              <option value="{{ $imported['key'] }}">{{ $imported['label'] }}</option>
            @endforeach
          </select>
          <small class="text-muted">Choose which imported business this category belongs to.</small>
        </div>
        <div class="form-group">
          <label class="control-label">Category Name</label>
          <input class="form-control" type="text" name="name" placeholder="e.g. Engine Parts" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Add Category</button>
      </form>
      @else
      <div class="alert alert-warning mb-0">
        <i class="fa fa-lock"></i> Import a business type first.
        <p class="small mb-0 mt-2">Use <strong>Choose Your Business Type</strong> or <strong>Custom Business Type</strong> above to register your business name and categories before adding new ones manually.</p>
      </div>
      @endif
    </div>
  </div>
  @endcan

  <div class="{{ Auth::user()->can('add_items') ? 'col-md-8' : 'col-md-12' }}">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h3 class="tile-title mb-0">Your Categories</h3>
          <p class="text-muted small mb-0 mt-1">Switch tabs to view categories for each business type.</p>
        </div>
        @can('delete_items')
          @if($categories->isNotEmpty())
          <form id="clearAllCategoriesForm" action="{{ route('categories.clear-all') }}" method="POST">
            @csrf @method('DELETE')
            <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearAllCategories">
              <i class="fa fa-eraser"></i> Clear All Categories
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
            All <span class="badge badge-secondary ml-1">{{ $categories->count() }}</span>
          </a>
        </li>
        @endif
        @foreach($importedTypes as $imported)
          @php $typeCount = $categoryCountsByType[$imported['key']] ?? 0; @endphp
          <li class="nav-item">
            <a class="nav-link category-type-tab {{ (count($importedTypes) === 1 || $defaultCategoryTab === $imported['key']) ? 'active' : '' }}" href="#" data-type="{{ $imported['key'] }}">
              {{ $imported['label'] }}
              @if(str_starts_with($imported['key'] ?? '', 'custom:'))
                <span class="badge badge-info ml-1">Custom</span>
              @endif
              <span class="badge badge-secondary ml-1">{{ $typeCount }}</span>
            </a>
          </li>
        @endforeach
        @if($otherCount > 0)
        <li class="nav-item">
          <a class="nav-link category-type-tab" href="#" data-type="other">
            Other <span class="badge badge-secondary ml-1">{{ $otherCount }}</span>
          </a>
        </li>
        @endif
      </ul>
      @endif

      <div class="tile-body px-0 pt-0">
        <div id="categoryTabEmpty" class="text-center text-muted py-4" style="display:none;">
          <i class="fa fa-folder-open-o fa-2x mb-2"></i>
          <p class="mb-0">No categories in this business type yet.</p>
        </div>
        <table class="table table-hover table-bordered mb-0" id="categoriesTable">
          <thead>
            <tr>
              <th>Name</th>
              @if($viewingAllBranches ?? false)
              <th>Branch</th>
              @endif
              <th>Items Count</th>
              <th>Actions</th>
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
                            <button type="submit" class="btn btn-sm btn-link text-success"><i class="fa fa-save"></i> Save</button>
                        </form>
                        @else
                        {{ $category->name }}
                        @endcan
                    </td>
                    @if($viewingAllBranches ?? false)
                    <td><span class="badge badge-light border">{{ $category->branch?->name ?? '—' }}</span></td>
                    @endif
                    <td><span class="badge badge-info">{{ $category->items_count ?? 0 }} items</span></td>
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

        Swal.fire('Select Branch', 'Please choose which branch this business type belongs to.', 'warning');
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
            Swal.fire('Select Business Types', 'Please click one or more business types above first.', 'warning');
            return;
        }

        const newSlotsNeeded = countNewTypeSlots(selectedTypes);

        if (typesLimit !== null && newSlotsNeeded > (typesLimit - typesUsed)) {
            Swal.fire({
                icon: 'warning',
                title: 'Plan limit reached',
                html: 'Your plan allows <strong>' + typesLimit + '</strong> business type(s).<br>You have <strong>' + typesUsed + '</strong> and selected import would need <strong>' + newSlotsNeeded + '</strong> new slot(s).<br><br>Clear all categories or upgrade your plan.',
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
            ? '<p class="mb-2"><strong>Branch:</strong> ' + branchLabel + '</p>'
            : '';

        Swal.fire({
            title: 'Import Selected Templates?',
            html: branchHtml + '<div style="text-align:left;max-height:220px;overflow-y:auto;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + previews + '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-magic"></i> Yes, Import!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnImportTemplate');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');
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
            Swal.fire('Missing Information', 'Please enter your business name and at least one category.', 'warning');
            return;
        }

        const preview = categoriesRaw
            .split(/[\r\n,]+/)
            .map(function(item) { return item.trim(); })
            .filter(function(item) { return item !== ''; })
            .join(', ');

        const branchLabel = selectedBranchLabel('#customBranchSelect');
        const branchHtml = branchLabel
            ? '<p class="mb-2"><strong>Branch:</strong> ' + branchLabel + '</p>'
            : '';

        Swal.fire({
            title: 'Import Custom Categories?',
            html: branchHtml + '<strong>' + businessName + '</strong><br><br>Categories to add:<br><br><div style="text-align:left;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + preview + '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-plus-circle"></i> Yes, Import!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnImportCustom');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');
                form.submit();
            }
        });
    });

    $(document).on('click', '.btn-delete-cat', function() {
        const btn = $(this);
        const name = btn.data('name');
        const form = btn.closest('form');

        Swal.fire({
            title: 'Delete "' + name + '"?',
            html: 'Items in this category will become <strong>uncategorized</strong>.<br>This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-trash"></i> Yes, Delete!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    $('#btnClearAllCategories').on('click', function() {
        Swal.fire({
            title: 'Clear ALL Categories?',
            html: 'This will remove <strong>{{ $categories->count() }}</strong> categories from your list.<br>Items in those categories will become <strong>uncategorized</strong>.<br><br>This cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-eraser"></i> Yes, Clear Everything!',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                const $btn = $('#btnClearAllCategories');
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing...');
                document.getElementById('clearAllCategoriesForm').submit();
            }
        });
    });
});
</script>
@endsection
