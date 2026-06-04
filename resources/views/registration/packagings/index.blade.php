@extends('layouts.app')

@section('title', 'Packaging Units')

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
  .business-type-card.configured {
    border-color: #28a745;
    background: #f6fff8;
  }
  .business-type-card.selected {
    border-color: #940000;
    background: #940000;
    color: #fff;
  }
  .business-type-card.selected .small,
  .business-type-card.selected .configured-badge {
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
  .business-type-card.imported {
    border-color: #28a745;
    background: #f6fff8;
  }
  .business-type-card.locked {
    opacity: 0.55;
    cursor: not-allowed;
    background: #f8f9fa;
  }
  .business-type-card.locked:hover {
    border-color: #e9ecef;
    background: #f8f9fa;
  }
  .packaging-type-tabs .nav-link {
    font-weight: 600;
    color: #495057;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    padding: 10px 16px;
  }
  .packaging-type-tabs .nav-link.active {
    color: #940000;
    border-bottom-color: #940000;
    background: transparent;
  }
  .packaging-type-tabs .nav-link:hover {
    color: #940000;
  }
</style>
@endsection

@section('content')
@php
  $configuredTypes = collect($importedTypes ?? []);
  $defaultUnits = $packagingTemplates['_default'] ?? [];
  $importedUnitKeys = collect($importedTypeKeys ?? []);
  $importedKeys = $configuredKeys ?? [];
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-archive"></i> Packaging & Units</h1>
    <p>Define how you sell your products (e.g. Piece, Box, Kg)</p>
  </div>
</div>

@if($configuredTypes->isNotEmpty())
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-check-circle"></i>
  <strong>
    Your business types
    @if($branchFilterId ?? null)
      for {{ $activeBranchName }}
    @endif
    :
  </strong>
  @foreach($configuredTypes as $configured)
    <span class="badge badge-light ml-1">{{ $configured['label'] ?? 'Business' }}</span>
  @endforeach
</div>
@elseif($branchFilterId ?? null)
<div class="alert alert-warning py-2 mb-3">
  <i class="fa fa-exclamation-triangle"></i>
  No business types configured for <strong>{{ $activeBranchName }}</strong> yet.
  <a href="{{ route('categories.index') }}">Import categories for this branch</a> first.
</div>
@endif

<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-credit-card"></i>
  Your <strong>{{ $business->plan->name ?? 'plan' }}</strong> allows
  <strong>{{ $business->businessTypesLimitLabel() }}</strong> business type(s).
  @if($typesLimit !== null)
    <span class="ml-1">
      Used: <strong>{{ $typesUsed }}</strong>
      @if($typesRemaining !== null)
        · Remaining: <strong>{{ $typesRemaining }}</strong>
      @endif
    </span>
  @endif
</div>

@can('add_items')
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">Choose Your Business Type</h3>
      <p class="text-muted small mb-3">Select a business type and import its standard measurement units. Each business type gets its own full unit list — shared names like "Bottle" are created separately per type.</p>

      <form id="unitForm" action="{{ route('packagings.import-templates') }}" method="POST">
        @csrf
        <input type="hidden" name="business_type_key" id="business_type_key" value="">

        <div class="business-type-grid mb-3">
          @foreach($businessTemplates as $key => $template)
            @if(($branchFilterId ?? null) && !in_array($key, $branchTypeKeys ?? [], true))
              @continue
            @endif
            @php
              $units = $packagingTemplates[$key] ?? $defaultUnits;
              $isConfigured = in_array($key, $importedKeys, true);
              $hasImportedUnits = $importedUnitKeys->contains($key);
              $isLocked = ! $isConfigured && $typesLimit !== null && ($typesRemaining ?? 0) <= 0;
            @endphp
            <div class="business-type-card {{ $isConfigured ? 'configured' : '' }} {{ $hasImportedUnits ? 'imported' : '' }} {{ $isLocked ? 'locked' : '' }}"
                 data-key="{{ $key }}"
                 data-label="{{ $template['label'] }}"
                 data-units="{{ implode(', ', $units) }}"
                 data-imported="{{ $isConfigured ? '1' : '0' }}">
              <i class="fa {{ $template['icon'] ?? 'fa-store' }}"></i>
              <div class="label-text">{{ $template['label'] }}</div>
              <div class="small text-muted">{{ count($units) }} units</div>
              @if($isLocked)
                <div class="small text-muted"><i class="fa fa-lock"></i> Plan limit</div>
              @elseif($hasImportedUnits)
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> Units imported</div>
              @elseif($isConfigured)
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> Your type</div>
              @endif
            </div>
          @endforeach

          @foreach($configuredTypes as $imported)
            @if(str_starts_with($imported['key'] ?? '', 'custom:'))
              @if(($branchFilterId ?? null) && !in_array($imported['key'] ?? '', $branchTypeKeys ?? [], true))
                @continue
              @endif
              @php $customUnits = $defaultUnits; @endphp
              <div class="business-type-card configured {{ $importedUnitKeys->contains($imported['key'] ?? '') ? 'imported' : '' }}"
                   data-key="{{ $imported['key'] }}"
                   data-label="{{ $imported['label'] }}"
                   data-units="{{ implode(', ', $customUnits) }}"
                   data-imported="1">
                <i class="fa fa-pencil"></i>
                <div class="label-text">{{ $imported['label'] }}</div>
                <div class="small text-muted">{{ count($customUnits) }} units</div>
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> Custom · Your type</div>
              </div>
            @endif
          @endforeach
        </div>

        <button type="button" class="btn btn-info" id="btnImportUnits" disabled onclick="confirmUnitImport()">
          <i class="fa fa-magic"></i> Import Units for Selected Type
        </button>

        @if($configuredTypes->isNotEmpty())
          <button type="button" class="btn btn-outline-info ml-2" onclick="confirmImportAll()">
            <i class="fa fa-download"></i> Import All
            @if($branchFilterId ?? null)
              for {{ $activeBranchName }}
            @endif
          </button>
        @endif
      </form>

      <form id="importAllForm" action="{{ route('packagings.import-templates') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="business_type_key" value="all">
      </form>
    </div>
  </div>
</div>
@endcan

<div class="row">
  @can('add_items')
  <div class="col-md-4">
    <div class="tile">
      <form id="clearAllForm" action="{{ route('packagings.clear-all') }}" method="POST" class="mb-3">
        @csrf @method('DELETE')
        <button type="button" class="btn btn-outline-danger btn-block btn-sm" onclick="confirmClearAll()">
          <i class="fa fa-eraser"></i> Clear All Units
        </button>
      </form>

      <h3 class="tile-title">Add Custom Unit</h3>
      <form action="{{ route('packagings.store') }}" method="POST">
        @csrf
        @if(count($packagingTabs) > 0 || $otherCount > 0)
        <div class="form-group">
          <label class="control-label">Business Type</label>
          <select class="form-control" name="source_business_type_key" id="addPackagingTypeKey">
            @foreach($packagingTabs as $tab)
              <option value="{{ $tab['key'] }}">{{ $tab['label'] }}</option>
            @endforeach
            <option value="other">Other / Manual</option>
          </select>
        </div>
        @endif
        <div class="form-group">
          <label class="control-label">Unit Name</label>
          <input class="form-control" type="text" name="name" placeholder="e.g. Bundle, Tray" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> Add Unit</button>
      </form>
    </div>
  </div>
  @endcan

  <div class="{{ Auth::user()->can('add_items') ? 'col-md-8' : 'col-md-12' }}">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h3 class="tile-title mb-0">Your Units</h3>
          <p class="text-muted small mb-0 mt-1">Switch tabs to view units imported for each business type.</p>
        </div>
      </div>

      @if(count($packagingTabs) > 0 || $otherCount > 0)
      <ul class="nav nav-tabs packaging-type-tabs border-bottom mb-3" id="packagingTypeTabs">
        @if(count($packagingTabs) > 1)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab {{ $defaultPackagingTab === 'all' ? 'active' : '' }}" href="#" data-type="all">
            All <span class="badge badge-secondary ml-1">{{ $packagings->count() }}</span>
          </a>
        </li>
        @endif
        @foreach($packagingTabs as $tab)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab {{ ((count($packagingTabs) === 1) || $defaultPackagingTab === $tab['key']) ? 'active' : '' }}" href="#" data-type="{{ $tab['key'] }}">
            {{ $tab['label'] }}
            @if($tab['is_custom'])
              <span class="badge badge-info ml-1">Custom</span>
            @endif
            <span class="badge badge-secondary ml-1">{{ $tab['count'] }}</span>
          </a>
        </li>
        @endforeach
        @if($otherCount > 0)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab" href="#" data-type="other">
            Other <span class="badge badge-secondary ml-1">{{ $otherCount }}</span>
          </a>
        </li>
        @endif
      </ul>
      @endif

      <div class="tile-body px-0 pt-0">
        <div id="packagingTabEmpty" class="text-center text-muted py-4" style="display:none;">
          <i class="fa fa-archive fa-2x mb-2"></i>
          <p class="mb-0">No units in this business type yet.</p>
        </div>
        <table class="table table-hover table-bordered mb-0" id="packagingsTable">
          <thead>
            <tr>
              <th>Unit Name</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($packagings as $unit)
                @php $rowType = $unit->source_business_type_key ?: 'other'; @endphp
                <tr data-business-type="{{ $rowType }}">
                    <td>
                        @can('edit_items')
                        <form action="{{ route('packagings.update', $unit->id) }}" method="POST" class="form-inline">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $unit->name }}" class="form-control form-control-sm mr-2" required>
                            <button type="submit" class="btn btn-sm btn-link text-success"><i class="fa fa-save"></i> Save</button>
                        </form>
                        @else
                        {{ $unit->name }}
                        @endcan
                    </td>
                    <td>
                        @can('delete_items')
                        <form action="{{ route('packagings.destroy', $unit->id) }}" method="POST" class="delete-pkg-form" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="button" class="btn btn-sm btn-danger btn-delete-pkg" data-name="{{ $unit->name }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr id="packagingTableEmptyRow">
                  <td colspan="2" class="text-center text-muted py-4">No packaging units yet. Select a business type above and import its units, or add a custom unit.</td>
                </tr>
            @endforelse
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
const importedKeys = @json($importedKeys);
const typesLimit = @json($typesLimit);
const typesUsed = @json($typesUsed);
const typesRemaining = @json($typesRemaining);

function showPlanLimitAlert() {
    Swal.fire({
        icon: 'warning',
        title: 'Plan limit reached',
        html: 'Your plan allows <strong>' + typesLimit + '</strong> business type(s).<br>You have used <strong>' + typesUsed + '</strong>.<br><br>Clear categories/units or upgrade your plan to add another business type.',
        confirmButtonColor: '#940000'
    });
}

$(document).ready(function() {
    let activePackagingTab = @json($defaultPackagingTab);

    function filterPackagingsByTab(type) {
        activePackagingTab = type;
        let visibleCount = 0;

        $('#packagingsTable tbody tr[data-business-type]').each(function() {
            const rowType = $(this).attr('data-business-type') || 'other';
            const show = type === 'all' || rowType === type;
            $(this).toggle(show);
            if (show) {
                visibleCount++;
            }
        });

        $('#packagingTabEmpty').toggle(visibleCount === 0 && $('#packagingsTable tbody tr[data-business-type]').length > 0);

        if ($('#addPackagingTypeKey').length && type !== 'all' && type !== 'other') {
            $('#addPackagingTypeKey').val(type);
        }
    }

    $('.packaging-type-tab').on('click', function(e) {
        e.preventDefault();
        $('.packaging-type-tab').removeClass('active');
        $(this).addClass('active');
        filterPackagingsByTab($(this).attr('data-type'));
    });

    if ($('#packagingTypeTabs').length) {
        filterPackagingsByTab(activePackagingTab);
    }

    $('.business-type-card').on('click', function() {
        if ($(this).hasClass('locked')) {
            showPlanLimitAlert();
            return;
        }

        $('.business-type-card').removeClass('selected');
        $(this).addClass('selected');
        document.getElementById('business_type_key').value = $(this).data('key');
        document.getElementById('btnImportUnits').disabled = false;
    });

    $(document).on('click', '.btn-delete-pkg', function() {
        const btn = $(this);
        const name = btn.data('name');
        const form = btn.closest('form');

        Swal.fire({
            title: 'Delete "' + name + '"?',
            html: 'This packaging unit will be permanently removed.<br>Items using this unit may be affected.',
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
});

function confirmUnitImport() {
    const selected = document.querySelector('.business-type-card.selected');
    if (!selected) {
        Swal.fire('Select a business type', 'Choose a business type from the list above to import its units.', 'warning');
        return;
    }

    const selectedKey = selected.dataset.key;
    const isNewType = importedKeys.indexOf(selectedKey) === -1;

    if (isNewType && typesLimit !== null && typesRemaining < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Plan limit reached',
            html: 'Your plan allows <strong>' + typesLimit + '</strong> business type(s).<br>You have used <strong>' + typesUsed + '</strong>.<br><br>Clear categories/units or upgrade your plan to add another business type.',
            confirmButtonColor: '#940000'
        });
        return;
    }

    const label = selected.dataset.label;
    const units = selected.dataset.units;

    Swal.fire({
        title: 'Import Units?',
        html: '<strong>' + label + '</strong><br><br>The following units will be added for this business type:<br><br><div style="text-align:left;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + units + '</div><br>' + (isNewType ? '<em>This will use 1 business type slot on your plan.</em><br><br>' : '') + '<strong>Units already imported for this business type will be skipped.</strong>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-magic"></i> Yes, Import!',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('unitForm').submit();
        }
    });
}

function confirmImportAll() {
    Swal.fire({
        title: 'Import All Business Types?',
        text: 'This adds standard units for every business type configured on your account. Each type receives its full unit list.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-download"></i> Yes, Import All',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('importAllForm').submit();
        }
    });
}

function confirmClearAll() {
    Swal.fire({
        title: 'Clear ALL Units?',
        text: 'This will remove every packaging unit in your list. This cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-eraser"></i> Yes, Clear Everything!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('clearAllForm').submit();
        }
    });
}
</script>
@endsection
