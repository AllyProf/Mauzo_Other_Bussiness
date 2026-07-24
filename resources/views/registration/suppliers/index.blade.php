@extends('layouts.app')

@section('title', 'Suppliers List')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-truck"></i> Suppliers Management</h1>
    <p>Manage your business suppliers and contact information</p>
  </div>
  <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Register Supplier</a>
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
@endsection
