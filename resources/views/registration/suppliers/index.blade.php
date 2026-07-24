@extends('layouts.app')

@section('title', 'Suppliers List')

@section('styles')
<style>
  .suppliers-page .supplier-title-actions { display: flex; flex-wrap: wrap; gap: 8px; }
  .suppliers-page .migrate-supplier-list {
    max-height: 240px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 10px;
    background: #fafafa;
  }
  .suppliers-page .migrate-supplier-list .custom-control { margin-bottom: 6px; }
  .suppliers-page .migrate-modal-header {
    background: #940000;
    color: #fff;
  }
  .suppliers-page .migrate-modal-header .close { color: #fff; opacity: 1; text-shadow: none; }
</style>
@endsection

@section('content')
<div class="suppliers-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-truck"></i> Suppliers Management</h1>
    <p>Manage your business suppliers and contact information</p>
  </div>
  <div class="supplier-title-actions">
    @if($canMigrateFromBranch ?? false)
      <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#migrateSuppliersModal">
        <i class="fa fa-exchange"></i> Migrate from branch
      </button>
    @endif
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Register Supplier</a>
  </div>
</div>

@if($activeBranchName ?? null)
  <div class="alert alert-info py-2 mb-3">
    <i class="fa fa-map-marker"></i> Showing suppliers for <strong>{{ $activeBranchName }}</strong>.
  </div>
@elseif($viewingAllBranches ?? false)
  <div class="alert alert-secondary py-2 mb-3">
    <i class="fa fa-sitemap"></i> Viewing suppliers for <strong>all branches</strong>. Switch branch in the header to filter.
  </div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>{{ __('tables.columns.name') }}</th>
              <th>Phone Number</th>
              <th>{{ __('tables.columns.email') }}</th>
              <th>{{ __('tables.columns.region') }}</th>
              @if($viewingAllBranches ?? false)
                <th>Branch</th>
              @endif
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($suppliers as $supplier)
                <tr>
                    <td><strong>{{ $supplier->name }}</strong></td>
                    <td>{{ $supplier->phone }}</td>
                    <td>{{ $supplier->email ?: 'N/A' }}</td>
                    <td><span class="badge badge-info">{{ $supplier->region ?: 'N/A' }}</span></td>
                    @if($viewingAllBranches ?? false)
                      <td>{{ $supplier->branch?->name ?? '—' }}</td>
                    @endif
                    <td>
                        <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i></a>
                        <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this supplier?')"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                  <td colspan="{{ ($viewingAllBranches ?? false) ? 6 : 5 }}" class="text-center text-muted py-4">
                    {{ __('tables.empty.suppliers') }}
                  </td>
                </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@if($canMigrateFromBranch ?? false)
<div class="modal fade" id="migrateSuppliersModal" tabindex="-1" role="dialog" aria-labelledby="migrateSuppliersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" action="{{ route('suppliers.migrate-from-branch') }}" id="migrateSuppliersForm">
        @csrf
        <div class="modal-header migrate-modal-header">
          <h5 class="modal-title mb-0" id="migrateSuppliersModalLabel">
            <i class="fa fa-exchange"></i> Migrate suppliers from another branch
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">
            Copy keeps suppliers on both branches. Move reassigns them to the destination.
            Matching phone numbers already on the destination are skipped.
          </p>

          <div class="form-group">
            <label class="font-weight-bold">From branch</label>
            <select name="from_branch_id" id="migrateFromBranch" class="form-control" required>
              <option value="">-- Select source branch --</option>
              @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">To branch</label>
            <select name="to_branch_id" id="migrateToBranch" class="form-control" required>
              <option value="">-- Select destination branch --</option>
              @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (int) ($branchFilterId ?? 0) === (int) $branch->id ? 'selected' : '' }}>
                  {{ $branch->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="font-weight-bold">Action</label>
            <div class="custom-control custom-radio">
              <input type="radio" id="migrateModeCopy" name="mode" value="copy" class="custom-control-input" checked>
              <label class="custom-control-label" for="migrateModeCopy">Copy (keep on source branch too)</label>
            </div>
            <div class="custom-control custom-radio">
              <input type="radio" id="migrateModeMove" name="mode" value="move" class="custom-control-input">
              <label class="custom-control-label" for="migrateModeMove">Move (reassign to destination)</label>
            </div>
          </div>

          <div class="form-group mb-1">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="font-weight-bold mb-0">Suppliers</label>
              <button type="button" class="btn btn-link btn-sm p-0" id="migrateSelectAll">Select all</button>
            </div>
            <div id="migrateSupplierList" class="migrate-supplier-list">
              <p class="text-muted small mb-0">Choose a source branch to load suppliers.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="migrateSubmitBtn" style="background:#940000;border-color:#940000;" disabled>
            <i class="fa fa-check"></i> Migrate
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
</div>
@endsection

@section('scripts')
@if($canMigrateFromBranch ?? false)
<script>
(function () {
  var listUrlTemplate = @json(route('suppliers.list-for-branch', ['branch' => '__ID__']));
  var fromSelect = document.getElementById('migrateFromBranch');
  var listEl = document.getElementById('migrateSupplierList');
  var submitBtn = document.getElementById('migrateSubmitBtn');
  var selectAllBtn = document.getElementById('migrateSelectAll');

  function listUrl(branchId) {
    return listUrlTemplate.replace('__ID__', String(branchId));
  }

  function updateSubmitState() {
    var checked = listEl.querySelectorAll('input[name="supplier_ids[]"]:checked').length;
    submitBtn.disabled = checked === 0;
  }

  function loadSuppliers(branchId) {
    if (!branchId) {
      listEl.innerHTML = '<p class="text-muted small mb-0">Choose a source branch to load suppliers.</p>';
      submitBtn.disabled = true;
      return;
    }

    listEl.innerHTML = '<p class="text-muted small mb-0"><i class="fa fa-spinner fa-spin"></i> Loading…</p>';
    submitBtn.disabled = true;

    fetch(listUrl(branchId), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var suppliers = data.suppliers || [];
        if (!suppliers.length) {
          listEl.innerHTML = '<p class="text-muted small mb-0">No suppliers on this branch.</p>';
          submitBtn.disabled = true;
          return;
        }

        listEl.innerHTML = suppliers.map(function (s) {
          return (
            '<div class="custom-control custom-checkbox">' +
              '<input type="checkbox" class="custom-control-input migrate-supplier-cb" id="mig-sup-' + s.id + '" name="supplier_ids[]" value="' + s.id + '" checked>' +
              '<label class="custom-control-label" for="mig-sup-' + s.id + '">' +
                (s.name || 'Supplier') +
                (s.phone ? ' <span class="text-muted">(' + s.phone + ')</span>' : '') +
              '</label>' +
            '</div>'
          );
        }).join('');
        updateSubmitState();
      })
      .catch(function () {
        listEl.innerHTML = '<p class="text-danger small mb-0">Could not load suppliers.</p>';
        submitBtn.disabled = true;
      });
  }

  fromSelect.addEventListener('change', function () {
    loadSuppliers(this.value);
  });

  listEl.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('migrate-supplier-cb')) {
      updateSubmitState();
    }
  });

  selectAllBtn.addEventListener('click', function () {
    var boxes = listEl.querySelectorAll('input[name="supplier_ids[]"]');
    if (!boxes.length) return;
    var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
    Array.prototype.forEach.call(boxes, function (b) { b.checked = !allChecked; });
    selectAllBtn.textContent = allChecked ? 'Select all' : 'Clear all';
    updateSubmitState();
  });
})();
</script>
@endif
@endsection
