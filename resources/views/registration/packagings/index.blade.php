@extends('layouts.app')

@section('title', 'Packaging Units')

@section('styles')
<style>
  .business-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
    max-height: 280px;
    overflow-y: auto;
    padding-right: 4px;
  }
  .business-type-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: all 0.15s ease;
    min-height: 88px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .business-type-card:hover {
    border-color: #940000;
    background: #fffbfb;
  }
  .business-type-card.selected {
    border-color: #940000;
    background: #940000;
    color: #fff;
  }
  .business-type-card.selected .small {
    color: rgba(255,255,255,0.85) !important;
  }
  .business-type-card i {
    font-size: 20px;
    margin-bottom: 6px;
  }
  .business-type-card .label-text {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.3;
  }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-archive"></i> Packaging & Units</h1>
    <p>Define how you sell your products (e.g. Piece, Box, Kg)</p>
  </div>
</div>

<div class="row">
  @can('add_items')
  <div class="col-md-4">
    <div class="tile">
      <h3 class="tile-title">Standard Units</h3>
      <p class="text-muted small">Import measurement units for the business type(s) you set up under <a href="{{ route('categories.index') }}">Categories</a>.</p>

      @if($unitImportOptions->isEmpty())
        <div class="alert alert-warning py-2 mb-0">
          <i class="fa fa-exclamation-triangle"></i>
          No business type imported yet.
          <a href="{{ route('categories.index') }}" class="alert-link">Go to Categories</a> and import your business type first.
        </div>
      @else
        <form id="unitForm" action="{{ route('packagings.import-templates') }}" method="POST">
          @csrf
          <input type="hidden" name="business_type_key" id="business_type_key" value="">

          <div class="business-type-grid mb-3">
            @foreach($unitImportOptions as $option)
            <div class="business-type-card"
                 data-key="{{ $option['key'] }}"
                 data-label="{{ $option['label'] }}"
                 data-units="{{ $option['units_preview'] }}">
              <i class="fa {{ $option['icon'] }}"></i>
              <div class="label-text">{{ $option['label'] }}</div>
              <div class="small text-muted">{{ $option['unit_count'] }} units</div>
            </div>
            @endforeach
          </div>

          <button type="button" class="btn btn-info btn-block mb-2" id="btnImportUnits" disabled onclick="confirmUnitImport()">
            <i class="fa fa-magic"></i> Import Units for Selected Type
          </button>
        </form>

        <form id="importAllForm" action="{{ route('packagings.import-templates') }}" method="POST" class="mb-2">
          @csrf
          <input type="hidden" name="business_type_key" value="all">
          <button type="button" class="btn btn-outline-info btn-block btn-sm" onclick="confirmImportAll()">
            <i class="fa fa-download"></i> Import All My Business Types
          </button>
        </form>
      @endif

      <form id="clearAllForm" action="{{ route('packagings.clear-all') }}" method="POST">
        @csrf @method('DELETE')
        <button type="button" class="btn btn-outline-danger btn-block btn-sm" onclick="confirmClearAll()">
          <i class="fa fa-eraser"></i> Clear All Units
        </button>
      </form>

      <hr>

      <h3 class="tile-title">Add Custom Unit</h3>
      <form action="{{ route('packagings.store') }}" method="POST">
        @csrf
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
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>Unit Name</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($packagings as $unit)
                <tr>
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
                <tr>
                  <td colspan="2" class="text-center text-muted py-4">No packaging units yet. Import from your business type(s) or add a custom unit.</td>
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
$(document).ready(function() {
    $('.business-type-card').on('click', function() {
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
        Swal.fire('Select a business type', 'Choose one of your imported business types first.', 'warning');
        return;
    }

    const label = selected.dataset.label;
    const units = selected.dataset.units;

    Swal.fire({
        title: 'Import Units?',
        html: '<strong>' + label + '</strong><br><br>The following units will be added:<br><br><div style="text-align:left;padding:8px;background:#f8f9fa;border-radius:4px;font-size:13px">' + units + '</div><br><strong>Existing units with the same name will not be duplicated.</strong>',
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
        text: 'This adds standard units for every business type you imported in Categories. Duplicates are skipped.',
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
