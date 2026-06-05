@extends('layouts.app')

@section('title', __('packaging.title'))

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
    <h1><i class="fa fa-archive"></i> {{ __('packaging.title') }}</h1>
    <p>{{ __('packaging.subtitle') }}</p>
  </div>
</div>

@if($configuredTypes->isNotEmpty())
<div class="alert alert-success py-2 mb-3">
  <i class="fa fa-check-circle"></i>
  <strong>
    @if($branchFilterId ?? null)
      {{ __('packaging.your_business_types_for', ['branch' => $activeBranchName]) }}
    @else
      {{ __('packaging.your_business_types') }}:
    @endif
  </strong>
  @foreach($configuredTypes as $configured)
    <span class="badge badge-light ml-1">{{ $configured['label'] ?? __('packaging.business') }}</span>
  @endforeach
</div>
@elseif($branchFilterId ?? null)
<div class="alert alert-warning py-2 mb-3">
  <i class="fa fa-exclamation-triangle"></i>
  {!! __('packaging.no_types_branch', ['branch' => '<strong>'.e($activeBranchName).'</strong>']) !!}
  <a href="{{ route('categories.index') }}">{{ __('packaging.import_categories_first') }}</a> {{ __('packaging.first') }}
</div>
@endif

<div class="alert alert-info py-2 mb-3">
  <i class="fa fa-credit-card"></i>
  @php $limitLabel = $typesLimit === null ? __('packaging.unlimited') : (string) $typesLimit; @endphp
  {{ __('packaging.plan_allowance', ['plan' => $business->plan->name ?? __('packaging.default_plan'), 'limit' => $limitLabel]) }}
  @if($typesLimit !== null)
    <span class="ml-1">
      {{ __('packaging.used', ['count' => $typesUsed]) }}
      @if($typesRemaining !== null)
        · {{ __('packaging.remaining', ['count' => $typesRemaining]) }}
      @endif
    </span>
  @endif
</div>

@can('add_items')
<div class="row mb-3">
  <div class="col-md-12">
    <div class="tile">
      <h3 class="tile-title">{{ __('packaging.choose_type') }}</h3>
      <p class="text-muted small mb-3">{{ __('packaging.choose_type_hint') }}</p>

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
              <div class="small text-muted">{{ __('packaging.units_count', ['count' => count($units)]) }}</div>
              @if($isLocked)
                <div class="small text-muted"><i class="fa fa-lock"></i> {{ __('packaging.plan_limit') }}</div>
              @elseif($hasImportedUnits)
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> {{ __('packaging.units_imported') }}</div>
              @elseif($isConfigured)
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> {{ __('packaging.your_type') }}</div>
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
                <div class="small text-muted">{{ __('packaging.units_count', ['count' => count($customUnits)]) }}</div>
                <div class="small configured-badge text-success"><i class="fa fa-check"></i> {{ __('packaging.custom_your_type') }}</div>
              </div>
            @endif
          @endforeach
        </div>

        <button type="button" class="btn btn-info" id="btnImportUnits" disabled onclick="confirmUnitImport()">
          <i class="fa fa-magic"></i> {{ __('packaging.import_selected') }}
        </button>

        @if($configuredTypes->isNotEmpty())
          <button type="button" class="btn btn-outline-info ml-2" onclick="confirmImportAll()">
            <i class="fa fa-download"></i> {{ __('packaging.import_all') }}
            @if($branchFilterId ?? null)
              {{ __('packaging.import_all_for', ['branch' => $activeBranchName]) }}
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
          <i class="fa fa-eraser"></i> {{ __('packaging.clear_all') }}
        </button>
      </form>

      <h3 class="tile-title">{{ __('packaging.add_custom') }}</h3>
      <form action="{{ route('packagings.store') }}" method="POST">
        @csrf
        @if(count($packagingTabs) > 0 || $otherCount > 0)
        <div class="form-group">
          <label class="control-label">{{ __('packaging.business_type') }}</label>
          <select class="form-control" name="source_business_type_key" id="addPackagingTypeKey">
            @foreach($packagingTabs as $tab)
              <option value="{{ $tab['key'] }}">{{ $tab['label'] }}</option>
            @endforeach
            <option value="other">{{ __('packaging.other_manual') }}</option>
          </select>
        </div>
        @endif
        <div class="form-group">
          <label class="control-label">{{ __('packaging.unit_name') }}</label>
          <input class="form-control" type="text" name="name" placeholder="{{ __('packaging.unit_name_placeholder') }}" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-plus"></i> {{ __('packaging.add_unit') }}</button>
      </form>
    </div>
  </div>
  @endcan

  <div class="{{ Auth::user()->can('add_items') ? 'col-md-8' : 'col-md-12' }}">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h3 class="tile-title mb-0">{{ __('packaging.your_units') }}</h3>
          <p class="text-muted small mb-0 mt-1">{{ __('packaging.tabs_hint') }}</p>
        </div>
      </div>

      @if(count($packagingTabs) > 0 || $otherCount > 0)
      <ul class="nav nav-tabs packaging-type-tabs border-bottom mb-3" id="packagingTypeTabs">
        @if(count($packagingTabs) > 1)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab {{ $defaultPackagingTab === 'all' ? 'active' : '' }}" href="#" data-type="all">
            {{ __('packaging.all') }} <span class="badge badge-secondary ml-1">{{ $packagings->count() }}</span>
          </a>
        </li>
        @endif
        @foreach($packagingTabs as $tab)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab {{ ((count($packagingTabs) === 1) || $defaultPackagingTab === $tab['key']) ? 'active' : '' }}" href="#" data-type="{{ $tab['key'] }}">
            {{ $tab['label'] }}
            @if($tab['is_custom'])
              <span class="badge badge-info ml-1">{{ __('packaging.custom') }}</span>
            @endif
            <span class="badge badge-secondary ml-1">{{ $tab['count'] }}</span>
          </a>
        </li>
        @endforeach
        @if($otherCount > 0)
        <li class="nav-item">
          <a class="nav-link packaging-type-tab" href="#" data-type="other">
            {{ __('packaging.other') }} <span class="badge badge-secondary ml-1">{{ $otherCount }}</span>
          </a>
        </li>
        @endif
      </ul>
      @endif

      <div class="tile-body px-0 pt-0">
        <div id="packagingTabEmpty" class="text-center text-muted py-4" style="display:none;">
          <i class="fa fa-archive fa-2x mb-2"></i>
          <p class="mb-0">{{ __('packaging.no_units_type') }}</p>
        </div>
        <table class="table table-hover table-bordered mb-0" id="packagingsTable">
          <thead>
            <tr>
              <th>{{ __('packaging.unit_name') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
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
                            <button type="submit" class="btn btn-sm btn-link text-success"><i class="fa fa-save"></i> {{ __('packaging.save') }}</button>
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
                  <td colspan="2" class="text-center text-muted py-4">{{ __('packaging.empty_table') }}</td>
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
const pkgI18n = @json(__('packaging.swal'));
const importedKeys = @json($importedKeys);
const typesLimit = @json($typesLimit);
const typesUsed = @json($typesUsed);
const typesRemaining = @json($typesRemaining);

function showPlanLimitAlert() {
    Swal.fire({
        icon: 'warning',
        title: pkgI18n.plan_limit,
        html: pkgI18n.plan_limit_html
            .replace(':limit', typesLimit)
            .replace(':used', typesUsed),
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
            title: pkgI18n.delete_title.replace(':name', name),
            html: pkgI18n.delete_html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-trash"></i> ' + pkgI18n.yes_delete,
            cancelButtonText: pkgI18n.cancel,
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
        Swal.fire(pkgI18n.select_type, pkgI18n.select_type_text, 'warning');
        return;
    }

    const selectedKey = selected.dataset.key;
    const isNewType = importedKeys.indexOf(selectedKey) === -1;

    if (isNewType && typesLimit !== null && typesRemaining < 1) {
        Swal.fire({
            icon: 'warning',
            title: pkgI18n.plan_limit,
            html: pkgI18n.plan_limit_html
                .replace(':limit', typesLimit)
                .replace(':used', typesUsed),
            confirmButtonColor: '#940000'
        });
        return;
    }

    const label = selected.dataset.label;
    const units = selected.dataset.units;

    Swal.fire({
        title: pkgI18n.import_units,
        html: '<strong>' + label + '</strong><br><br>' + pkgI18n.units_will_add + '<br><br><div style="text-align:left;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + units + '</div><br>' + (isNewType ? '<em>' + pkgI18n.uses_slot + '</em><br><br>' : '') + '<strong>' + pkgI18n.skip_existing + '</strong>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-magic"></i> ' + pkgI18n.yes_import,
        cancelButtonText: pkgI18n.cancel,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('unitForm').submit();
        }
    });
}

function confirmImportAll() {
    Swal.fire({
        title: pkgI18n.import_all_title,
        text: pkgI18n.import_all_text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-download"></i> ' + pkgI18n.yes_import_all,
        cancelButtonText: pkgI18n.cancel,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('importAllForm').submit();
        }
    });
}

function confirmClearAll() {
    Swal.fire({
        title: pkgI18n.clear_all_title,
        text: pkgI18n.clear_all_text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-eraser"></i> ' + pkgI18n.yes_clear,
        cancelButtonText: pkgI18n.cancel
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('clearAllForm').submit();
        }
    });
}
</script>
@endsection
